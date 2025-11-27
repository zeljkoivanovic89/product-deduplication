<?php
namespace Test\ProductDeduplication\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Backend\Model\Auth\Session as AuthSession;

/**
 * Class ProcessAjaxStart
 */
class ProcessAjaxStart extends Action
{
    /**
     * @var Filesystem
     */
    protected Filesystem $filesystem;

    /**
     * @var AuthSession
     */
    protected AuthSession $authSession;

    /**
     * @param Action\Context $context
     * @param Filesystem $filesystem
     * @param AuthSession $authSession
     */
    public function __construct(
        Action\Context $context,
        Filesystem $filesystem,
        AuthSession $authSession
    ) {
        parent::__construct($context);
        $this->filesystem = $filesystem;
        $this->authSession = $authSession;
    }

    /**
     * @return ResponseInterface|ResultInterface
     * @throws FileSystemException
     */
    public function execute(): ResultInterface|ResponseInterface
    {
        $response = ['success' => false, 'message' => ''];
        $file = $this->getRequest()->getFiles('csv_file');

        if (!$file || !isset($file['tmp_name']) || empty($file['tmp_name'])) {
            $response['message'] = __('No CSV file uploaded.');
            return $this->getResponse()->representJson(json_encode($response));
        }

        $varDir = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        $tmpPath = 'tmp/product_deduplication/';
        $varDir->create($tmpPath);

        $destFileName = $tmpPath . uniqid('product_dedupe_') . '_' . basename($file['name']);
        $destAbs = $varDir->getAbsolutePath($destFileName);

        if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
            $response['message'] = __('Failed to save uploaded file.');
            return $this->getResponse()->representJson(json_encode($response));
        }

        // Count total rows
        $fh = new \SplFileObject($destAbs);
        $fh->setFlags(\SplFileObject::READ_CSV);
        $total = 0;
        foreach ($fh as $row) {
            if ($row === [null] || $row === false) continue;
            $total++;
        }

        // Save job info in session
        $jobId = uniqid('dedupe_job_');
        $this->_getSession()->setData($jobId, [
            'file' => $destAbs,
            'processed' => 0,
            'total' => $total,
            'validRows' => [],
            'invalidRows' => []
        ]);

        $response['success'] = true;
        $response['jobId'] = $jobId;
        return $this->getResponse()->representJson(json_encode($response));
    }

    /**
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Test_ProductDeduplication::menu');
    }
}
