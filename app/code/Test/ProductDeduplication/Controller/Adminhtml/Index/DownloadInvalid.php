<?php
namespace Test\ProductDeduplication\Controller\Adminhtml\Index;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Test\ProductDeduplication\Model\DeduplicationFactory;

/**
 * Class DownloadInvalid
 */
class DownloadInvalid extends Action
{
    /**
     * @var FileFactory
     */
    protected FileFactory $fileFactory;

    /**
     * @var DeduplicationFactory
     */
    protected DeduplicationFactory $dedupFactory;

    /**
     * @param Action\Context $context
     * @param FileFactory $fileFactory
     * @param DeduplicationFactory $dedupFactory
     */
    public function __construct(
        Action\Context $context,
        FileFactory $fileFactory,
        DeduplicationFactory $dedupFactory
    ) {
        parent::__construct($context);
        $this->fileFactory = $fileFactory;
        $this->dedupFactory = $dedupFactory;
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws Exception
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        $id = $this->getRequest()->getParam('id');
        $model = $this->dedupFactory->create()->load($id);

        if (!$model->getId()) {
            $this->messageManager->addErrorMessage(__('No record found.'));
            return $this->_redirect('*/*/history');
        }

        $payload = json_decode($model->getPayload(), true);
        $invalidRows = $payload['invalid'] ?? [];

        if (empty($invalidRows)) {
            $this->messageManager->addNoticeMessage(__('No invalid rows for this file.'));
            return $this->_redirect('*/*/history');
        }

        // Generate csv in memory
        $csvLines = [];
        // Header
        $csvLines[] = ['Index', 'Row Data', 'Reason'];
        foreach ($invalidRows as $row) {
            $csvLines[] = array_merge([$row['index']], $row['row'], [$row['reason']]);
        }

        // convert csv to string
        $csvContent = '';
        $fp = fopen('php://temp', 'r+');
        foreach ($csvLines as $line) {
            fputcsv($fp, $line);
        }
        rewind($fp);
        $csvContent = stream_get_contents($fp);
        fclose($fp);

        $fileName = 'invalid_rows_' . $model->getId() . '.csv';

        return $this->fileFactory->create(
            $fileName,
            $csvContent,
            \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
