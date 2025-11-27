<?php
namespace Test\ProductDeduplication\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Test\ProductDeduplication\Model\DeduplicationFactory;
use Magento\Framework\Controller\Result\RedirectFactory;

/**
 * Class Remove
 */
class Remove extends Action
{
    /**
     * @var DeduplicationFactory
     */
    protected DeduplicationFactory $dedupFactory;

    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @param Action\Context $context
     * @param DeduplicationFactory $dedupFactory
     * @param RedirectFactory $resultRedirectFactory
     */
    public function __construct(
        Action\Context $context,
        DeduplicationFactory $dedupFactory,
        RedirectFactory $resultRedirectFactory
    ) {
        parent::__construct($context);
        $this->dedupFactory = $dedupFactory;
        $this->resultRedirectFactory = $resultRedirectFactory;
    }

    /**
     * @return ResponseInterface|Redirect|ResultInterface
     */
    public function execute(): ResultInterface|ResponseInterface|Redirect
    {
        $id = $this->getRequest()->getParam('id');
        $resultRedirect = $this->resultRedirectFactory->create();

        if ($id) {
            try {
                $model = $this->dedupFactory->create()->load($id);
                if ($model->getId()) {
                    $model->delete();
                    $this->messageManager->addSuccessMessage(__('Row deleted successfully.'));
                }
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());
            }
        }

        return $resultRedirect->setPath('*/*/history');
    }
}
