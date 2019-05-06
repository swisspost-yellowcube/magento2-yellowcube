<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

/**
 * Product Low Stock Report Collection
 *
 * @author      Magento Core Team <core@magentocommerce.com>
 */
namespace Swisspost\YellowCube\Model;

use Magento\Framework\App\ResourceConnection\SourceProviderInterface;
use Magento\Framework\Exception\LocalizedException;
use Traversable;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @api
 * @since 100.0.2
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
