<?php

namespace Swisspost\YellowCube\Model;

use Magento\Ui\DataProvider\AbstractDataProvider;

class StockDataProvider extends AbstractDataProvider
{
    public function __construct($name, $primaryFieldName, $requestFieldName, StockCollection $collection, array $meta = [], array $data = [])
    {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collection;

    }


}
