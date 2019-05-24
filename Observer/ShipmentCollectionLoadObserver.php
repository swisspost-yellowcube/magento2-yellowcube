<?php
namespace Swisspost\YellowCube\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Grid\Collection as ShipmentCollection;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Order\Grid\Collection as OrderShipmentCollection;

/**
 * Swisspost YellowCube Shipment Collection Load Observer.
 *
 * Based on \Temando\Shipping\Observer\ShipmentCollectionLoadObserver.
 */
class ShipmentCollectionLoadObserver implements ObserverInterface
{
    /**
     * Add the shipment status column, aliased with namespace prefix to avoid collisions.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {
        $collection = $observer->getData('collection');
        if ($collection instanceof ShipmentCollection || $collection instanceof OrderShipmentCollection) {
            $index = 'shipment_status';
            $alias = 'yellowcube_shipment_status';

            try {
                $collection->getSelect()->columns([$alias => $index]);
                $where = $collection->getSelect()->getPart(\Zend_Db_Select::WHERE);
                $collection->getSelect()->setPart(\Zend_Db_Select::WHERE, str_replace($alias, $index, $where));
            } catch (\Zend_Db_Select_Exception $exception) {
                return;
            }
        }
    }
}
