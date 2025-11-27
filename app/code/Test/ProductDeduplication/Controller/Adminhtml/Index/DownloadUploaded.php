<?php
namespace Test\ProductDeduplication\Controller\Adminhtml\Index;

use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Test\ProductDeduplication\Model\DeduplicationFactory;

/**
 * Class DownloadUploaded
 */
class DownloadUploaded extends Action
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
            $this->messageManager->addErrorMessage(__('File not found.'));
            return $this->_redirect('*/*/index');
        }

        $filePath = $model->getFilePath();
        $fileName = basename($filePath);

        return $this->fileFactory->create(
            $fileName,
            [
                'type' => 'filename',
                'value' => $filePath,
                'rm' => false
            ],
            \Magento\Framework\App\Filesystem\DirectoryList::VAR_DIR
        );
    }
}
