<?php
namespace Test\ProductDeduplication\Model\Consumer;

use Magento\Framework\Json\Helper\Data as JsonHelper;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Psr\Log\LoggerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class RunConsumer
 */
class RunConsumer
{
    /**
     * @var JsonHelper
     */
    protected JsonHelper $jsonHelper;

    /**
     * @var ProductRepositoryInterface
     */
    protected ProductRepositoryInterface $productRepository;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var StoreManagerInterface
     */
    protected StoreManagerInterface $storeManager;

    /**
     * @param JsonHelper $jsonHelper
     * @param ProductRepositoryInterface $productRepository
     * @param LoggerInterface $logger
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        JsonHelper $jsonHelper,
        ProductRepositoryInterface $productRepository,
        LoggerInterface $logger,
        StoreManagerInterface $storeManager
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->productRepository = $productRepository;
        $this->logger = $logger;
        $this->storeManager = $storeManager;
    }

    /**
     * Process message from queue
     *
     * @param string $messageBody
     */
    public function process($messageBody)
    {
        $this->logger->info('RunConsumer start: ' . $messageBody);

        $data = $this->jsonHelper->jsonDecode($messageBody, true);

        if (empty($data['duplicates']) || !is_array($data['duplicates'])) {
            $this->logger->info('No duplicates found in message: ' . $messageBody);
            return;
        }

        // Only use global store (admin) to avoid multiple loads
        $storeId = 0;

        foreach ($data['duplicates'] as $sku) {
            try {
                // Load product in admin/global context
                $product = $this->productRepository->get($sku, false, $storeId);

                // Skip if already disabled
                if ($product->getStatus() == Status::STATUS_DISABLED) {
                    $this->logger->info("SKU $sku already disabled, skipping.");
                    continue;
                }

                $this->logger->info("Processing SKU: $sku, current global status: " . $product->getStatus());

                // Set status to disabled
                $product->setStatus(Status::STATUS_DISABLED);
                $this->productRepository->save($product);

                // Verify after save
                $reloaded = $this->productRepository->get($sku, false, $storeId);
                $newStatus = $reloaded->getStatus();
                $this->logger->info("After save, global status of SKU $sku: $newStatus");

            } catch (NoSuchEntityException $e) {
                $this->logger->warning("SKU does not exist: $sku");
            } catch (\Exception $e) {
                $this->logger->error("Error disabling SKU $sku: " . $e->getMessage());
            }
        }

        $this->logger->info('RunConsumer finished processing message.');
    }
}
