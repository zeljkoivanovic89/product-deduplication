<?php
namespace Test\ProductDeduplication\Model\ResourceModel\Deduplication;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Test\ProductDeduplication\Model\Deduplication as Model;
use Test\ProductDeduplication\Model\ResourceModel\Deduplication as ResourceModel;

/**
 * Class Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Model::class, ResourceModel::class);
    }
}
