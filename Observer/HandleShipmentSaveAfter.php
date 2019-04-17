<?php

namespace Swisspost\YellowCube\Observer;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order\Shipment;
use Magento\Shipping\Model\Shipment\Request;
use Swisspost\YellowCube\Helper\Data;
use Swisspost\YellowCube\Model\Shipping\Carrier\Carrier;
use Swisspost\YellowCube\Model\Synchronizer;

class HandleShipmentSaveAfter implements \Magento\Framework\Event\ObserverInterface
{

    /**
     * @var \Swisspost\YellowCube\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var array
     */
    protected $_attributeProductIds;

    /**
     * @var Synchronizer
     */
    protected $synchronizer;

    /**
     * @var \Magento\Shipping\Model\ShippingFactory
     */
    protected $shippingFactory;

    /**
     * @var \Magento\Shipping\Model\CarrierFactory
     */
    protected $carrierFactory;

    /**
     * HandleProductSaveBefore constructor.
     * @param Data $dataHelper
     */
    public function __construct(
        \Swisspost\YellowCube\Helper\Data $dataHelper,
        \Swisspost\YellowCube\Model\Synchronizer $synchronizer,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $catalogProductTypeConfigurable,
        \Magento\Shipping\Model\ShippingFactory $shippingFactory,
        \Magento\Shipping\Model\CarrierFactory $carrierFactory
    )
    {
        $this->dataHelper = $dataHelper;
        $this->synchronizer = $synchronizer;
        $this->shippingFactory = $shippingFactory;
        $this->carrierFactory = $carrierFactory;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     *
     * @throws LocalizedException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /* @var $shipment Shipment */
        $shipment = $observer->getShipment();
        if (strpos($shipment->getOrder()->getShippingMethod(), 'yellowcube_') === 0 && $shipment->getOrder()->getIsInProcess()) {
            /* @var $carrier \Magento\Shipping\Model\Carrier\AbstractCarrier */
            $carrier = $this->carrierFactory->createIfActive('yellowcube', $shipment->getStoreId());
            if ($carrier) {
                $request = new Request();
                $request->setOrderShipment($shipment);
                $carrier->requestToShipment($request);
            }
        }
    }
}
