<?php
namespace Swisspost\YellowCube\Model\ResourceModel;

class YellowCubeStock extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{
    const TABLE_NAME = 'yellowcube_stock';
    const ID_FIELD_NAME = 'id';

    protected function _construct()
    {
        $this->_init(self::TABLE_NAME, self::ID_FIELD_NAME);
    }
}