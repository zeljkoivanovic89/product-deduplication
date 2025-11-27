<?php
namespace Test\ProductDeduplication\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Class Deduplication
 */
class Deduplication extends AbstractDb
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init('test_product_deduplication', 'id');
    }
}
