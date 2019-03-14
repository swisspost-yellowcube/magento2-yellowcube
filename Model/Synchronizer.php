<?php

namespace Swisspost\YellowCube\Model;

use Swisspost\YellowCube\Helper\Data;

class Synchronizer
{
    const SYNC_ACTION_INSERT                = 'insert';
    const SYNC_ACTION_UPDATE                = 'update';
    const SYNC_ACTION_DEACTIVATE            = 'deactivate';
    const SYNC_ORDER_WAB                    = 'order_wab';
    const SYNC_ORDER_UPDATE                 = 'order_update';
    const SYNC_INVENTORY                    = 'bar';
    const SYNC_WAR                          = 'war';

    /**
     * @var \Zend_Queue
     */
    protected $_queue;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $catalogResourceModelProductCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $catalogProductFactory;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $catalogResourceModelProductCollectionFactory,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->catalogResourceModelProductCollectionFactory = $catalogResourceModelProductCollectionFactory;
        $this->catalogProductFactory = $catalogProductFactory;
    }
    public function action(\Magento\Catalog\Model\Product $product, $action = self::SYNC_ACTION_INSERT)
    {
        $this->getQueue()->send(\Zend_Json::encode(array(
            'action' => $action,
            'website_id' => $product->getWebsiteId(),
            'plant_id' => $this->getHelper()->getPlantId(),
            'deposit_number' => $this->getHelper()->getDepositorNumber(),
            'product_id' => $product->getId(),
            'product_sku' => $product->getSku(),
            'product_weight' => $product->getWeight(),
            'product_name' => $product->getName(),
            'product_length' => $product->getData('yc_dimension_length'),
            'product_width' => $product->getData('yc_dimension_width'),
            'product_height' => $product->getData('yc_dimension_height'),
            'product_uom' => $product->getData('yc_dimension_uom'),
            'product_volume' => $product->getData('yc_dimension_height') * $product->getData('yc_dimension_length') *  $product->getData('yc_dimension_width'),
            'tara_factor' => $this->scopeConfig->getValue(Data::CONFIG_TARA_FACTOR, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->storeManager->getWebsite($product->getWebsiteId())->getDefaultStore()->getId()),
            'product_ean' => $product->getData('yc_ean_code'),
            'product_ean_type' => $product->getData('yc_ean_type'),
            'product_lot_management' => $product->getData('yc_requires_lot_management'),
        )));

        return $this;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return $this
     */
    public function insert(\Magento\Catalog\Model\Product $product)
    {
        $this->action($product);
        return $this;
    }

    /**
     * @return $this
     */
    public function updateAll()
    {
        /** @var \Magento\Catalog\Model\ResourceModel\Product\Collection $collection */
        $collection = $this->catalogResourceModelProductCollectionFactory->create();
        $collection->addAttributeToSelect(array(
            'name',
            'weight',
            'yc_sync_with_yellowcube',
            'yc_dimension_length',
            'yc_dimension_width',
            'yc_dimension_height',
            'yc_dimension_uom',
            'yc_ean_type',
            'yc_ean_code',

        ));
        $collection->addFieldToFilter('yc_sync_with_yellowcube', 1);

        foreach ($collection as $product) {
            $this->action($product, self::SYNC_ACTION_INSERT);
        }

        return $this;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return $this
     */
    public function update(\Magento\Catalog\Model\Product $product)
    {
        $this->action($product, self::SYNC_ACTION_UPDATE);
        return $this;
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return $this
     */
    public function deactivate(\Magento\Catalog\Model\Product $product)
    {
        $this->action($product, self::SYNC_ACTION_DEACTIVATE);
        return $this;
    }

    /**
     * @param \Magento\Shipping\Model\Shipment\Request $request
     * @return $this
     */
    public function ship(\Magento\Shipping\Model\Shipment\Request $request)
    {
        $order = $request->getOrderShipment();
        $realOrder = $order->getOrder();
        $helper = Mage::helper('swisspost_yellowcube');

        $locale = $this->scopeConfig->getValue('general/locale/code', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $request->getStoreId());
        $locale = explode('_', $locale);

        $positionItems = array();
        foreach ($order->getAllItems() as $item) {
            $product = $this->catalogProductFactory->create()->load($item->getProductId());
            $positionItems[] = array(
                'article_id' => $item->getProductId(),
                'article_number' => $item->getSku(),
                'article_ean' => $product->getData('yc_ean_code'),
                'article_title' => $item->getName(),
                'article_qty' => $item->getQty(),
            );
        }

        $this->getQueue()->send(\Zend_Json::encode(array(
            'action'    => self::SYNC_ORDER_WAB,
            'store_id'  => $request->getStoreId(),
            'plant_id'  => $this->getHelper()->getPlantId($request->getStoreId()),

            // Order Header
            'deposit_number'    => $this->getHelper()->getDepositorNumber($request->getStoreId()),
            'order_id'          => $order->getOrderId(),
            'order_increment_id' => $realOrder->getIncrementId(),
            'order_date'        => date('Ymd'),

            // Partner Address
            'partner_type'          => Swisspost_YellowCube_Helper_Data::PARTNER_TYPE,
            'partner_number'        => $this->getHelper()->getPartnerNumber($request->getStoreId()),
            'partner_reference'     => $this->getHelper()->getPartnerReference(
                $request->getRecipientContactPersonName(),
                $request->getRecipientAddressPostalCode()
            ),
            'partner_name'          => $request->getRecipientContactPersonName(),
            'partner_name2'         => $request->getRecipientContactCompanyName(),
            'partner_street'        => $request->getRecipientAddressStreet1(),
            'partner_name3'         => $request->getRecipientAddressStreet2(),
            'partner_country_code'  => $request->getRecipientAddressCountryCode(),
            'partner_city'          => $request->getRecipientAddressCity(),
            'partner_zip_code'      => $request->getRecipientAddressPostalCode(),
            'partner_phone'         => $request->getRecipientContactPhoneNumber(),
            'partner_email'         => $request->getRecipientEmail(),
            'partner_language'      => $locale[0], // possible values expected de|fr|it|en ...

            // ValueAddedServices - AdditionalService
            'service_basic_shipping'      => $helper->getRealCode($request->getShippingMethod()),
            'service_additional_shipping' => $helper->getAdditionalShipping($request->getShippingMethod()),

            // Order Positions
            'items' => $positionItems
        )));

        return $this;
    }

    /**
     * @return $this
     */
    public function bar()
    {
        $this->getQueue()->send(\Zend_Json::encode(array(
            'action' => self::SYNC_INVENTORY
        )));
        return $this;
    }

    /**
     * @return $this
     */
    public function war()
    {
        $this->getQueue()->send(\Zend_Json::encode(array(
            'action' => self::SYNC_WAR
        )));
        return $this;
    }

    /**
     * @return \Zend_Queue
     */
    public function getQueue()
    {
        if (null === $this->_queue) {
            $this->_queue = Mage::getModel('swisspost_yellowcube/queue')->getInstance();
        }
        return $this->_queue;
    }

    /**
     * @return Swisspost_YellowCube_Helper_Data
     */
    public function getHelper()
    {
        return Mage::helper('swisspost_yellowcube');
    }
}
