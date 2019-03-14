<?php

namespace Swisspost\YellowCube\Model;


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
     * - catalog_product_attribute_update_before
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function handleAttributeProductSave(\Magento\Framework\Event\Observer $observer)
    {
        $productIds = $observer->getEvent()->getProductIds();
        $attributesData = $observer->getEvent()->getAttributesData();
        $storeId = $observer->getEvent()->getStoreId();

        $actionResource = $this->catalogResourceModelProductActionFactory->create();
        $yc = $actionResource->getAttribute('yc_sync_with_yellowcube');
        $ycl = $actionResource->getAttribute('yc_dimension_length');
        $ycw = $actionResource->getAttribute('yc_dimension_width');
        $ych = $actionResource->getAttribute('yc_dimension_height');
        $ycuom = $actionResource->getAttribute('yc_dimension_uom');
        $weight = $actionResource->getAttribute('weight');

        if (!$yc->getId()) {
            return;
        }

        foreach ($productIds as $key => $productId) {
            $productYCSync = $this->getAttributeData($productId, $storeId, $yc->getId());
            $productYCUom = $this->getAttributeData($productId, $storeId, $ycuom->getId(), 'varchar');
            $productYCLength = $this->getAttributeData($productId, $storeId, $ycl->getId(), 'decimal');
            $productYCWidth = $this->getAttributeData($productId, $storeId, $ycw->getId(), 'decimal');
            $productYCHeight = $this->getAttributeData($productId, $storeId, $ych->getId(), 'decimal');
            $productWeight = $this->getAttributeData($productId, $storeId, $weight->getId(), 'decimal');

            /**
             * If length/width/height in product is null => do nothing and doesn't allow to change the value
             */
            if (empty ($productYCLength) && empty($productYCWidth) && empty($productYCHeight)
                && empty($attributesData['yc_dimension_length']) && empty($attributesData['yc_dimension_width']) && empty($attributesData['yc_dimension_height'])
                && !empty($attributesData['yc_sync_with_yellowcube'])
            ) {

                if ((int) $productYCSync['value'] !== (int) $attributesData['yc_sync_with_yellowcube']) {
                    // Prepare to revert the changes - Note: cannot modify $productIds per reference as it is Mage_Catalog_Model_Product_Action::updateAttributes
                    $this->_attributeProductIds[$productId]['yc_sync_with_yellowcube'] = $productYCSync;
                }
                continue;
            }

            if (count($productYCSync) > 0) {
                if (isset($attributesData['yc_sync_with_yellowcube']) && (int) $productYCSync['value'] !== (int) $attributesData['yc_sync_with_yellowcube']) {
                    switch ((int) $attributesData['yc_sync_with_yellowcube']) {
                        case 0:
                            $this->getSynchronizer()->deactivate($this->catalogProductFactory->create()->load($productId));
                            break;
                        case 1:
                            $this->getSynchronizer()->insert($this->catalogProductFactory->create()->load($productId));
                            break;
                    }
                } else if ((int) $productYCSync['value']) {
                    // We handle size and weight changes if YC is enabled
                    if ((isset($attributesData['yc_dimension_length']) && $productYCLength['value'] != $attributesData['yc_dimension_length'])
                        || (isset($attributesData['yc_dimension_width']) && $productYCWidth['value'] != $attributesData['yc_dimension_width'])
                        || (isset($attributesData['yc_dimension_height']) && $productYCHeight['value'] != $attributesData['yc_dimension_height'])
                        || (isset($attributesData['yc_dimension_uom']) && $productYCUom['value'] != $attributesData['yc_dimension_uom'])
                        || (isset($attributesData['weight']) && $productWeight['value'] != $attributesData['weight'])
                    ) {
                        $this->getSynchronizer()->update($this->catalogProductFactory->create()->load($productId));
                    }
                }
            }
        }
    }

    /**
     * Event
     * - catalog_product_attribute_update_after
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function handleAttributeProductSaveAfter(\Magento\Framework\Event\Observer $observer)
    {
        if (count($this->_attributeProductIds) > 0) {
            $actionResource = $this->catalogResourceModelProductActionFactory->create();
            foreach ($this->_attributeProductIds as $productId => $attributes) {
                foreach ($attributes as $key => $attribute) {
                    if ($key == 'yc_sync_with_yellowcube') {
                        // Revert the changes done during the mass update of product attributes only if products doesn't have length/width/height
                        $actionResource->updateAttributes(array($productId), array($key => $attribute['value']), $attribute['store_id']);
                    }
                }
            }
        }
        return $this;
    }

    /**
     * @param $productId
     * @param $storeId
     * @param $attributeId
     * @param string $type
     * @return array
     */
    public function getAttributeData($productId, $storeId, $attributeId, $type = 'int')
    {
        $resource = $this->resourceConnection;
        $read = $resource->getConnection('catalog_read');

        $select = $read->select()
            ->from($resource->getTableName('catalog_product_entity_' . $type))
            ->where('attribute_id = ?', $attributeId)
            ->where('entity_id = ?', $productId)
            ->where('store_id = ?', $storeId);

        return $read->fetchRow($select);
    }

    /**
     * Event
     * - catalog_product_save_before
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function handleBeforeProductSave(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getDataObject();
        $helper = Mage::helper('swisspost_yellowcube');

        if ((bool)$product->getData('yc_sync_with_yellowcube') && $product->getWeight() > 30) {
            throw new \Magento\Framework\Exception\LocalizedException($helper->__('The weight cannot be higher than 30 kilograms if YellowCube is enabled.'));
        }
    }

    /**
     * Event
     * - catalog_product_save_after
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function handleProductSave(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getDataObject();

        // @todo Make it work with multiple websites. Note: value of yc_sync_with_yellowcube on product save doesn't reflect the value of the default view if "Use default Value" is checked

        $helper = Mage::helper('swisspost_yellowcube');
        if (!$helper->isConfigured(/* $storeId */) && (bool)$product->getData('yc_sync_with_yellowcube')) {
            throw new \Magento\Framework\Exception\LocalizedException($helper->__('Please, configure YellowCube before to save the product having YellowCube option enabled.'));
        } else if (!$helper->isConfigured(/* $storeId */)) {
            return $this;
        }

        /**
         * Scenario
         *
         * - product is disabled or enabled => no change
         * - yc_sync_with_yellowcube is Yes/No
         *   - From No to Yes => insert into YC
         *   - From No to No => no change
         *   - From Yes to No => deactivate from YC
         *
         * - if duplicate, we do nothing as the attribute 'yc_sync_with_yellowcube' = 0
         */

        if ((bool)$product->getData('yc_sync_with_yellowcube') && $this->hasDataChangedFor($product, array('yc_sync_with_yellowcube'))) {
            $this->getSynchronizer()->insert($product);
            return $this;
        }

        if (!(bool)$product->getData('yc_sync_with_yellowcube') && $this->hasDataChangedFor($product, array('yc_sync_with_yellowcube'))) {
            $this->getSynchronizer()->deactivate($product);
            return $this;
        }

        if (!(bool)$product->getData('yc_sync_with_yellowcube')) {
            return $this;
        }

        if ($this->hasDataChangedFor($product, array('name', 'weight', 'yc_dimension_length', 'yc_dimension_width', 'yc_dimension_height', 'yc_dimension_uom'))) {
            $this->getSynchronizer()->update($product);
            return $this;
        }
    }

    /**
     * Event
     * - catalog_product_delete_before
     *
     * @param \Magento\Framework\Event\Observer $observer
     */
    public function handleProductDelete(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getDataObject();
        $this->getSynchronizer()->deactivate($product);
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

    /**
     * Event
     * - sales_order_shipment_save_before
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function handleShipmentSaveBefore(\Magento\Framework\Event\Observer $observer)
    {
        /* @var $shipment Mage_Sales_Model_Order_Shipment */
        $shipment = $observer->getShipment();
        $carrier = $shipment->getOrder()->getShippingCarrier();

        if ($carrier instanceof Swisspost_YellowCube_Model_Shipping_Carrier_Rate && $shipment->getOrder()->getIsInProcess()) {
            $this->shippingShippingFactory->create()->requestToShipment($shipment);
        }

        return $this;
    }

    /**
     * Add a message to the queue to sync the YellowCube Inventory with Magento Products
     *
     * @return $this
     */
    public function handleInventory()
    {
        $this->getSynchronizer()->bar();
        return $this;
    }

    /**
     * Add a message to the queue to sync the YellowCube Inventory with Magento Products
     *
     * @return $this
     */
    public function handleWar()
    {
        $this->getSynchronizer()->war();
        return $this;
    }

    /**
     * @return Swisspost_YellowCube_Model_Synchronizer
     */
    public function getSynchronizer()
    {
        return Mage::getSingleton('swisspost_yellowcube/synchronizer');
    }

    /**
     * Check whether specified attribute has been changed for given entity
     *
     * @param \Magento\Framework\Model\AbstractModel $entity
     * @param string|array $key
     * @return bool
     */
    public function hasDataChangedFor(\Magento\Framework\Model\AbstractModel $entity, $key)
    {
        if (is_array($key)) {
            foreach ($key as $code) {
                if ($entity->getOrigData($code) !== $entity->getData($code)) {
                    return true;
                }
            }
            return false;
        }
        return $entity->getOrigData($key) !== $entity->getData($key);
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return bool
     */
    public function isProductNew(\Magento\Catalog\Model\Product $product)
    {
        return $product->isObjectNew()
        || (($product->getOrigData('sku') == '') && (strlen($product->getData('sku')) > 0));
    }

    /**
     * Add Composer Autoloader for our YellowCube library
     *
     * Event
     * - resource_get_tablename
     * - add_spl_autoloader
     */
    public function addAutoloader()
    {
        if (!self::$shouldAdd) {
            return;
        }

        /** @var \\Composer\Autoload\ClassLoader $loader */
        $loader = require BP . '/vendor/autoload.php';
        $loader->register();

        self::$shouldAdd = false;
        return $this;
    }

    public function disableLotFields(\Magento\Framework\Event\Observer $observer)
    {
        $event = $observer->getEvent();
        $product = $event->getProduct();
        $product->lockAttribute('yc_lot_info');
        $product->lockAttribute('yc_most_recent_expiration_date');
    }

    /**
     * @return \Magento\Framework\Session\Generic
     */
    protected function _getSession()
    {
        return Mage::getSingleton('core/session');
    }
}
