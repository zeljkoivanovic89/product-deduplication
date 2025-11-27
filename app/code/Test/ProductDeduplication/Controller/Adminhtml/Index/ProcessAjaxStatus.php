<?php
namespace Test\ProductDeduplication\Controller\Adminhtml\Index;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Filesystem;
use Magento\Backend\Model\Auth\Session as AuthSession;
use Test\ProductDeduplication\Model\DeduplicationFactory;

/**
 * Class ProcessAjaxStatus
 */
class ProcessAjaxStatus extends Action
{
    /**
     * @var Filesystem
     */
    protected Filesystem $filesystem;

    /**
     * @var DeduplicationFactory
     */
    protected DeduplicationFactory $deduplicationFactory;

    /**
     * @var AuthSession
     */
    protected AuthSession $authSession;

    /**
     * @param Action\Context $context
     * @param Filesystem $filesystem
     * @param AuthSession $authSession
     * @param DeduplicationFactory $deduplicationFactory
     */
    public function __construct(
        Action\Context $context,
        Filesystem $filesystem,
        AuthSession $authSession,
        DeduplicationFactory $deduplicationFactory
    ) {
        parent::__construct($context);
        $this->filesystem = $filesystem;
        $this->authSession = $authSession;
        $this->deduplicationFactory = $deduplicationFactory;
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws Exception
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        $jobId = $this->getRequest()->getParam('jobId');
        $job = $this->_getSession()->getData($jobId);

        if (!$job) {
            return $this->getResponse()->representJson(json_encode([
                'success' => false,
                'message' => __('Job not found.')
            ]));
        }

        $file = $job['file'];
        $processed = $job['processed'];
        $validRows = $job['validRows'];
        $invalidRows = $job['invalidRows'];

        $fh = new \SplFileObject($file);
        $fh->setFlags(\SplFileObject::READ_CSV);
        $rowIndex = 0;
        $maxPerPoll = 200;
        $processedInThisPoll = 0;

        foreach ($fh as $row) {
            $rowIndex++;
            if ($row === [null] || $row === false) continue;
            if ($rowIndex <= $processed) continue;

            $row = array_map(fn($v) => is_string($v) ? trim($v) : $v, $row);

            $validation = $this->validateRow($row, $rowIndex);
            $isValid = $validation['valid'];
            $reason = $validation['reason'];

            if ($isValid) $validRows[] = ['index' => $rowIndex, 'row' => $row];
            else $invalidRows[] = ['index' => $rowIndex, 'row' => $row, 'reason' => $reason];

            $processed++;
            $processedInThisPoll++;

            if ($processedInThisPoll >= $maxPerPoll) break;
        }

        // Update session
        $this->_getSession()->setData($jobId, [
            'file' => $file,
            'processed' => $processed,
            'total' => $job['total'],
            'validRows' => $validRows,
            'invalidRows' => $invalidRows
        ]);

        $percent = $job['total'] > 0 ? round(($processed / $job['total']) * 100) : 0;
        if ($processed >= $job['total']) $percent = 100;

        // Save to DB if finished
        if ($processed >= $job['total']) {
            $deduplication = $this->deduplicationFactory->create();
            $deduplication->setData([
                'admin_user_id' => $this->authSession->getUser()->getId(),
                'file_path' => $file,
                'payload' => json_encode([
                    'valid' => $validRows,
                    'invalid' => $invalidRows
                ])
            ]);
            $deduplication->save();
        }

        return $this->getResponse()->representJson(json_encode([
            'success' => true,
            'processed' => $processed,
            'total' => $job['total'],
            'validCount' => count($validRows),
            'invalidCount' => count($invalidRows),
            'percent' => $percent,
            'invalidDetails' => array_map(fn($i) => ['index'=>$i['index'],'reason'=>$i['reason']??''], $invalidRows)
        ]));
    }

    /**
     * Validate Row
     *
     * @param array $row
     * @param int $rowIndex
     * @return array
     */
    protected function validateRow(array $row, int $rowIndex): array
    {
        $row = array_map(fn($v) => is_string($v) ? trim($v) : $v, $row);

        $skuThatStays = $row[0] ?? '';
        $duplicates = array_slice($row, 1);
        $allSkus = array_merge([$skuThatStays], $duplicates);

        $skuPattern = '/^[A-Za-z0-9._\-\s]{1,64}$/';

        switch (true) {
            case count($row) < 2:
                return ['valid' => false, 'reason' => 'Less than 2 columns'];

            case in_array('', $allSkus, true):
                return ['valid' => false, 'reason' => 'SKU cannot be empty'];

            case array_reduce($allSkus, fn ($bad, $sku) => $bad ?: !preg_match($skuPattern, $sku), false):
                return ['valid' => false, 'reason' => 'SKU contains invalid characters (allowed: A-Z, a-z, 0-9, ., -, _, space)'];

            case count($duplicates) === 0:
                return ['valid' => false, 'reason' => 'No duplicate SKUs provided'];

            case count($allSkus) !== count(array_unique($allSkus)):
                return ['valid' => false, 'reason' => 'Duplicate SKUs detected inside row'];
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Test_ProductDeduplication::menu');
    }
}
