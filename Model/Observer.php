<?php

namespace Swisspost\YellowCube\Model;

use Swisspost\YellowCube\Model\Shipping\Carrier\Source\Rate;

class Observer
{
    const CONFIG_PATH_PSR0NAMESPACES = 'global/psr0_namespaces';

    static $shouldAdd = true;

    protected $_attributeProductIds = array();

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\ActionFactory
     */
    protected $catalogResourceModelProductActionFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $catalogProductFactory;

    /**
     * @var \Magento\Framework\App\ResourceConnection
     */
    protected $resourceConnection;

    /**
     * @var \Magento\Shipping\Model\ShippingFactory
     */
    protected $shippingShippingFactory;

    /**
     * @var \Magento\Framework\Session\Generic
     */
    protected $generic;

    public function __construct(
        \Magento\Catalog\Model\ResourceModel\Product\ActionFactory $catalogResourceModelProductActionFactory,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Magento\Framework\App\ResourceConnection $resourceConnection,
        \Magento\Shipping\Model\ShippingFactory $shippingShippingFactory,
        \Magento\Framework\Session\Generic $generic
    ) {
        $this->catalogResourceModelProductActionFactory = $catalogResourceModelProductActionFactory;
        $this->catalogProductFactory = $catalogProductFactory;
        $this->resourceConnection = $resourceConnection;
        $this->shippingShippingFactory = $shippingShippingFactory;
        $this->generic = $generic;
    }



    /**
     * Event
     * - catalog_product_delete_before
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function handleProductDelete(\Magento\Framework\Event\Observer $observer)
    {

    }

    /**
     * Event:
     * - catalog_model_product_duplicate
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function handleProductDuplicate(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Catalog\Model\Product $newProduct */
        $newProduct = $observer->getEvent()->getNewProduct();
        $newProduct->setData('yc_sync_with_yellowcube', 0);

        return $this;
    }
}
