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
    const SYNC_INVENTORY                    = 'Bar';
    const SYNC_WAR                          = 'War';

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
        $this->publish(static::SYNC_ORDER_WAB, [
            'shipment_id'  => $shipment->getId(),
        ]);

        return $this;
    }

    /**
     * @return $this
     */
    public function bar()
    {
        $this->publish(static::SYNC_INVENTORY);
        return $this;
    }

    /**
     * @return $this
     */
    public function war()
    {
        $this->publish(static::SYNC_WAR);
        return $this;
    }

    /**
     * Publishes an action to the message queue.
     *
     * @param string $action
     *   The action.
     * @param array $data
     *   Additional data.
     */
    protected function publish(string $action, array $data = [])
    {
        $data['action'] = $action;
        $this->publisher->publish('yellowcube.sync', $this->jsonSerializer->serialize($data));
    }
}
