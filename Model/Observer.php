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

    /**
     * Event
     * - sales_order_shipment_save_before
     *
     * @param \Magento\Framework\Event\Observer $observer
     * @return $this
     */
    public function handleShipmentSaveBefore(\Magento\Framework\Event\Observer $observer)
    {
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


    /**
     * @return \Magento\Framework\Session\Generic
     */
    protected function _getSession()
    {
        return Mage::getSingleton('core/session');
    }
}
