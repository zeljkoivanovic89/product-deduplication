<?php
namespace Test\ProductDeduplication\Ui\DataProvider;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Test\ProductDeduplication\Model\Deduplication;
use Test\ProductDeduplication\Model\ResourceModel\Deduplication\Collection;
use Test\ProductDeduplication\Model\ResourceModel\Deduplication\CollectionFactory;

/**
 * Class DataProvider
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Get Data
     * @return array
     */
    public function getData(): array
    {
        $this->collection->load();
        $items = $this->collection->getItems();

        $result = ['totalRecords' => $this->collection->getSize(), 'items' => []];

        foreach ($items as $item) {
            /** @var Deduplication $item */
            $itemData = $item->getData();
            $itemData['valid_count'] = $item->getValidCount();
            $itemData['invalid_count'] = $item->getInvalidCount();
            $itemData['invalid_details'] = $item->getInvalidDetails();
            $result['items'][] = $itemData;
        }

        return $result;
    }
}
