<?php
namespace Test\ProductDeduplication\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\Framework\Controller\Result\JsonFactory;

/**
 * Class Run
 */
class Run extends Action
{
    const TOPIC_NAME = 'product_deduplication.run';

    /**
     * @var PublisherInterface
     */
    protected PublisherInterface $publisher;

    /**
     * @var JsonHelper
     */
    protected JsonHelper $jsonHelper;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resource;

    /**
     * @var JsonFactory
     */
    protected JsonFactory $resultJsonFactory;

    /**
     * @param Action\Context $context
     * @param PublisherInterface $publisher
     * @param JsonHelper $jsonHelper
     * @param ResourceConnection $resource
     * @param JsonFactory $resultJsonFactory
     */
    public function __construct(
        Action\Context $context,
        PublisherInterface $publisher,
        JsonHelper $jsonHelper,
        ResourceConnection $resource,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->publisher = $publisher;
        $this->jsonHelper = $jsonHelper;
        $this->resource = $resource;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @return ResponseInterface|Json|ResultInterface
     */
    public function execute(): Json|ResultInterface|ResponseInterface
    {
        $result = $this->resultJsonFactory->create();
        $connection = $this->resource->getConnection();
        $table = $this->resource->getTableName('test_product_deduplication');

        // take last uplaoded row
        $select = $connection->select()
            ->from($table)
            ->order('id DESC')
            ->limit(1);

        $row = $connection->fetchRow($select);

        if (!$row) {
            return $result->setData([
                'success' => false,
                'message' => 'No data found in test_product_deduplication table'
            ]);
        }

        // decode JSON payload
        $data = $this->jsonHelper->jsonDecode($row['payload'], true);
        if (empty($data['valid']) || !is_array($data['valid'])) {
            return $result->setData([
                'success' => false,
                'message' => 'No valid rows in payload'
            ]);
        }

        $count = 0;

        foreach ($data['valid'] as $validRow) {
            $skus = $validRow['row'] ?? [];
            if (empty($skus) || !is_array($skus)) {
                continue;
            }

            $primary = array_shift($skus); // first stay active
            $duplicates = $skus;           // others go to disable

            if (!empty($duplicates)) {
                $payload = [
                    'primary' => $primary,
                    'duplicates' => $duplicates
                ];

                // publish to message queue
                $this->publisher->publish(
                    self::TOPIC_NAME,
                    $this->jsonHelper->jsonEncode($payload)
                );

                $count += count($duplicates);
            }
        }

        return $result->setData([
            'success' => true,
            'message' => "$count SKU(s) sent to queue for disabling"
        ]);
    }
}
