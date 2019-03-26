<?php

namespace Swisspost\YellowCube\Model;

use Magento\Framework\Serialize\Serializer\Json;
use Swisspost\YellowCube\Helper\Data;

class Synchronizer
{
    const SYNC_ACTION_INSERT                = 'Insert';
    const SYNC_ACTION_UPDATE                = 'Update';
    const SYNC_ACTION_DEACTIVATE            = 'Deactivate';
    const SYNC_ORDER_WAB                    = 'OrderWab';
    const SYNC_ORDER_UPDATE                 = 'OrderUpdate';
    const SYNC_INVENTORY                    = 'Bar';
    const SYNC_WAR                          = 'War';

    /**
     * @var \Zend_Queue
     */
    protected $_queue;

    /**
     * @var \Swisspost\YellowCube\Helper\Data
     */
    protected $dataHelper;

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

    /**
     * @var \Magento\Framework\MessageQueue\PublisherInterface
     */
    private $publisher;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $jsonSerializer;

    public function __construct(
        Data $dataHelper,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $catalogResourceModelProductCollectionFactory,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Magento\Framework\MessageQueue\PublisherInterface $publisher,
        Json $json_serializer
    ) {
        $this->dataHelper = $dataHelper;
        $this->storeManager = $storeManager;
        $this->catalogResourceModelProductCollectionFactory = $catalogResourceModelProductCollectionFactory;
        $this->catalogProductFactory = $catalogProductFactory;
        $this->publisher = $publisher;
        $this->jsonSerializer = $json_serializer;
    }
    public function action(\Magento\Catalog\Model\Product $product, $action = self::SYNC_ACTION_INSERT)
    {
        $this->publisher->publish('yellowcube.sync', $this->jsonSerializer->serialize([
            'action' => $action,
            'website_id' => $product->getWebsiteId(),
            'plant_id' => $this->dataHelper->getPlantId(),
            'deposit_number' => $this->dataHelper->getDepositorNumber(),
            'product_id' => $product->getId(),
            'product_sku' => $product->getSku(),
            'product_weight' => $product->getWeight(),
            'product_name' => $product->getName(),
            'product_length' => $product->getData('ts_dimensions_length'),
            'product_width' => $product->getData('ts_dimensions_width'),
            'product_height' => $product->getData('ts_dimensions_height'),
            'product_uom' => $product->getData('ts_dimensions_uom'),
            'product_volume' => $product->getData('ts_dimensions_height') * $product->getData('ts_dimensions_length') *  $product->getData('ts_dimensions_width'),
            'tara_factor' => $this->dataHelper->getTaraFactor(),
            'product_ean' => $product->getData('yc_ean_code'),
            'product_ean_type' => $product->getData('yc_ean_type'),
            'product_lot_management' => $product->getData('yc_requires_lot_management'),
        ]));

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
        $collection->addAttributeToSelect([
            'name',
            'weight',
            'yc_sync_with_yellowcube',
            'ts_dimensions_length',
            'ts_dimensions_width',
            'ts_dimensions_height',
            'ts_dimensions_uom',
            'yc_ean_type',
            'yc_ean_code',

        ]);
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
        $shipment = $request->getOrderShipment();
        $order = $shipment->getOrder();

        $locale = $this->dataHelper->getConfigValue('general/locale/code', $request->getStoreId());
        $locale = explode('_', $locale);

        $positionItems = [];
        foreach ($shipment->getAllItems() as $item) {
            $product = $this->catalogProductFactory->create()->load($item->getProductId());
            $positionItems[] = [
                'article_id' => $item->getProductId(),
                'article_number' => $item->getSku(),
                'article_ean' => $product->getData('yc_ean_code'),
                'article_title' => $item->getName(),
                'article_qty' => $item->getQty(),
            ];
        }

        $this->publisher->publish('yellowcube.sync', $this->jsonSerializer->serialize([
            'action'    => self::SYNC_ORDER_WAB,
            'store_id'  => $shipment->getStoreId(),
            'plant_id'  => $this->dataHelper->getPlantId($shipment->getStoreId()),

            // Order Header
            'deposit_number'    => $this->dataHelper->getDepositorNumber($shipment->getStoreId()),
            'order_id'             => $shipment->getOrderId(),
            'order_date'        => date('Ymd'),

            // Partner Address
            'partner_type'          => Data::PARTNER_TYPE,
            'partner_number'        => $this->dataHelper->getPartnerNumber($shipment->getStoreId()),
            'partner_reference'     => $this->dataHelper->getPartnerReference(
                $shipment->getShippingAddress()->getName(),
                $shipment->getShippingAddress()->getPostcode()
            ),
            'partner_name'          => $shipment->getShippingAddress()->getName(),
            'partner_name2'         => $shipment->getShippingAddress()->getCompany(),
            'partner_street'        => $shipment->getShippingAddress()->getStreetLine(1),
            'partner_name3'         => $shipment->getShippingAddress()->getStreetLine(2),
            'partner_country_code'  => $shipment->getShippingAddress()->getCountryId(),
            'partner_city'          => $shipment->getShippingAddress()->getCity(),
            'partner_zip_code'      => $shipment->getShippingAddress()->getPostcode(),
            'partner_phone'         => $shipment->getShippingAddress()->getTelephone(),
            'partner_email'         => $shipment->getShippingAddress()->getEmail(),
            'partner_language'      => $locale[0], // possible values expected de|fr|it|en ...

            // ValueAddedServices - AdditionalService
            'service_basic_shipping'      => $this->dataHelper->getRealCode(str_replace('yellowcube_', '', $order->getShippingMethod())),
            'service_additional_shipping' => $this->dataHelper->getAdditionalShipping(str_replace('yellowcube_', '', $order->getShippingMethod())),

            // Order Positions
            'items' => $positionItems
        ]));

        return $this;
    }

    /**
     * @return $this
     */
    public function bar()
    {
        $this->publisher->publish('yellowcube.sync', $this->jsonSerializer->serialize([
            'action' => self::SYNC_INVENTORY,
        ]));
        return $this;
    }

    /**
     * @return $this
     */
    public function war()
    {
        $this->publisher->publish('yellowcube.sync', $this->jsonSerializer->serialize([
            'action' => self::SYNC_WAR,
        ]));
        return $this;
    }
}
