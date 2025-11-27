<?php

namespace Test\ProductDeduplication\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Class Actions
 */
class Actions extends Column
{
    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $item[$this->getData('name')] = [
                    'remove' => [
                        'href' => $this->context->getUrl('productdeduplication/index/remove', ['id' => $item['id']]),
                        'label' => __('Remove')
                    ],
                    'download_uploaded' => [
                        'href' => $this->context->getUrl('productdeduplication/index/downloaduploaded', ['id' => $item['id']]),
                        'label' => __('Download Uploaded File')
                    ],
                    'download_invalid' => [
                        'href' => $this->context->getUrl('productdeduplication/index/downloadinvalid', ['id' => $item['id']]),
                        'label' => __('Download Invalid Rows')
                    ],
                ];
            }
        }

        return $dataSource;
    }
}
