<?php

namespace Test\ProductDeduplication\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Class Deduplication
 */
class Deduplication extends AbstractModel
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(\Test\ProductDeduplication\Model\ResourceModel\Deduplication::class);
    }

    /**
     * Return decoded payload array from JSON column
     * @return array
     */
    public function getPayloadData(): array
    {
        $payload = $this->getData('payload');
        return $payload ? json_decode($payload, true) : [];
    }

    /**
     * Count valid rows
     * @return int
     */
    public function getValidCount(): int
    {
        return count($this->getPayloadData()['valid'] ?? []);
    }

    /**
     * Count invalid rows
     * @return int
     */
    public function getInvalidCount(): int
    {
        return count($this->getPayloadData()['invalid'] ?? []);
    }

    /**
     * Get invalid details
     * @return string
     */
    public function getInvalidDetails(): string
    {
        $invalid = $this->getPayloadData()['invalid'] ?? [];
        $details = '';
        $maxLength = 100;

        foreach ($invalid as $row) {
            $line = "index: {$row['index']}, reason: {$row['reason']}";

            if ($details !== '') {
                $line = '<br/>' . $line;
            }

            $details .= $line;

            if (mb_strlen($details) > $maxLength) {
                $details = mb_substr($details, 0, $maxLength) . '...';
                break;
            }
        }

        return $details;
    }


}
