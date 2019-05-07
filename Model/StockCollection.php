<?php

namespace Swisspost\YellowCube\Model;

/**
 */
class StockCollection implements \IteratorAggregate, \Magento\Framework\App\ResourceConnection\SourceProviderInterface
{

    public function getMainTable()
    {
        return YellowCubeStock::TABLE_NAME;
    }

    public function getIdFieldName()
    {
        return 'id';
    }

    public function addFieldToSelect($fieldName, $alias = null)
    {
        $foo = 1;
    }

    public function getSelect()
    {
        $foo = 1;
    }

    public function addFieldToFilter($attribute, $condition = null)
    {
        $foo = 1;
    }

    public function getIterator()
    {
        $foo = 1;
    }
}
