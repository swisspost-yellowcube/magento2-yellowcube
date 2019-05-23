<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;
use Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository;

class Bar extends ProcessorAbstract implements ProcessorInterface
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $catalogProductFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\ActionFactory
     */
    protected $catalogResourceModelProductActionFactory;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Shipment\Item\CollectionFactory
     */
    protected $shipmentItemCollectionFactory;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var SourceItemsSaveInterface
     */
    protected $sourceItemSave;

    /**
     * @var \Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory
     */
    protected $sourceItemFactory;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $jsonSerializer;

    /**
     * @var YellowCubeShipmentItemRepository
     */
    protected $yellowCubeShipmentItemRepository;

    /**
     * @var \Swisspost\YellowCube\Model\YellowCubeStock
     */
    protected $yellowCubeStock;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Swisspost\YellowCube\Helper\Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Product\ActionFactory $catalogResourceModelProductActionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Shipment\Item\CollectionFactory $salesResourceModelOrderShipmentItemCollectionFactory,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        SourceItemsSaveInterface $sourceItemsSave,
        \Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory $sourceItemFactory,
        \Magento\Framework\Serialize\Serializer\Json $jsonSerializer,
        YellowCubeShipmentItemRepository $yellowCubeShipmentItemRepository,
        \Swisspost\YellowCube\Model\YellowCubeStock $yellowCubeStock,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        parent::__construct($logger, $dataHelper, $clientFactory);
        $this->catalogProductFactory = $catalogProductFactory;
        $this->storeManager = $storeManager;
        $this->productRepository = $productRepository;
        $this->catalogResourceModelProductActionFactory = $catalogResourceModelProductActionFactory;
        $this->shipmentItemCollectionFactory = $salesResourceModelOrderShipmentItemCollectionFactory;
        $this->sourceItemSave = $sourceItemsSave;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->jsonSerializer = $jsonSerializer;
        $this->yellowCubeShipmentItemRepository = $yellowCubeShipmentItemRepository;
        $this->yellowCubeStock = $yellowCubeStock;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @param array $data
     *
     * @throws \Zend_Json_Exception
     */
    public function process(array $data)
    {
        $this->dataHelper->allowLockedAttributeChanges(true);

        $inventory = $this->getYellowCubeService()->getInventoryWithMetadata();

        $this->logger->info(__('YellowCube reports %1 products with a stock level', count($inventory->getArticles())));
        $this->yellowCubeStock->updateStock($inventory->getArticles());

        if (!$inventory->getArticles()) {
            return;
        }

        $lotSummary = [];

        /* @var $article \YellowCube\BAR\Article */
        foreach ($inventory->getArticles() as $article) {
            $articleNo = $article->getArticleNo();
            $articleLot = $article->getLot();

            //todo @psa make sure this gives NULL if empty
            if (!is_null($article->getLot())) {
                $lotSummary[$articleNo]['qty'] = $article->getQuantityUOM()->get();
                $lotSummary[$articleNo]['lotInfo'] = 'Lot: ' . $articleLot . " Quantity: " . (int)$article->getQuantityUOM()->get() . ' ExpDate: ' . $this->convertYCDate($article->getBestBeforeDate()) . PHP_EOL;
                $lotSummary[$articleNo]['recentExpDate'] = $article->getBestBeforeDate();

                foreach ($inventory->getArticles() as $article2) {
                    $article2No = $article2->getArticleNo();
                    $article2Lot = $article2->getLot();
                    //only do this if its not the lot already iterating
                    if ($articleNo == $article2No && $articleLot != $article2Lot) {
                        $lotSummary[$articleNo]['qty'] = $lotSummary[$articleNo]['qty'] + $article2->getQuantityUOM()->get();
                        $lotSummary[$articleNo]['lotInfo'] = $lotSummary[$articleNo]['lotInfo'] . 'Lot: ' . $article2Lot . " Quantity: " . (int)$article2->getQuantityUOM()->get() . ' ExpDate: ' . $this->convertYCDate($article2->getBestBeforeDate()) . PHP_EOL;
                        $lotSummary[$articleNo]['recentExpDate'] = $article2->getBestBeforeDate() < $lotSummary[$articleNo]['recentExpDate'] ? $article2->getBestBeforeDate() : $lotSummary[$articleNo]['recentExpDate'];
                    }
                }
            } else {
                $lotSummary[$articleNo]['qty'] = $article->getQuantityUOM()->get();
                $lotSummary[$articleNo]['lotInfo'] = null;
                $lotSummary[$articleNo]['recentExpDate'] = null;
            }
        }

        if ($this->dataHelper->getDebug()) {
            $this->logger->debug(print_r($lotSummary, true));
        }

        foreach ($lotSummary as $articleNo => $articleData) {
            $this->update($articleNo, $articleData, $inventory->getTimestamp());
        }

        // Ensure that the stock for all enabled products that are not in the response is 0.
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter('sku', array_keys($lotSummary), 'nin')
            ->addFilter('yc_sync_with_yellowcube', 1)
            ->create();
        $missing_products = $this->productRepository->getList($searchCriteria);
        foreach ($missing_products->getItems() as $missing_product) {
            $this->updateProductQuantity($missing_product->getSku(), 0);
        }

        $this->dataHelper->allowLockedAttributeChanges(false);
    }

    /**
     * Update a product.
     *
     * @param string $sku
     *   The product SKU.
     * @param array $data
     *   Data to update.
     *
     * @throws \Zend_Json_Exception
     */
    public function update($sku, array $data, $inventory_timestamp)
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // insert your error handling here
            $this->logger->info(__('Product %1 inventory cannot be synchronized from YellowCube into Magento because it does not exist.', $sku));
            return;
        }

        if ($product->getData('yc_stock') != $data['qty']) {
            $product->setData('yc_stock', $data['qty']);
            $this->productRepository->save($product);
        }

        /**
         * YellowCube lot - Handle the Lot information for the product
         */
        if (!is_null($data['recentExpDate'])) { //only do lot info if there is lot info available
            /*$action = $this->catalogResourceModelProductActionFactory->create();
            $action->updateAttributes([$sku], [
                'yc_lot_info' => $data['lotInfo'],
                'yc_most_recent_expiration_date' => $this->convertYCDate($data['recentExpDate'])
            ], $this->storeManager->getStore(0)->getId());*/
        }

        /**
         * YellowCube stock - qty of products not yet shipped = new stock
         */
        $shipmentItemsCollection = $this->shipmentItemCollectionFactory->create();
        $shipmentItemsCollection
            ->addFieldToFilter('entity_id', ['in' => $this->yellowCubeShipmentItemRepository->getUnshippedShipmentItemIdsByProductId($product->getId(), strtotime($inventory_timestamp))])
            ->addFieldToSelect('additional_data')
            ->addFieldToSelect('order_item_id')
            ->addFieldToSelect('qty');

        $qtyToDecrease = 0;
        foreach ($shipmentItemsCollection->getItems() as $shipment_item) {
            $qtyToDecrease += $shipment_item->getQty();
        }

        $quantity = $data['qty'] - $qtyToDecrease;
        $this->updateProductQuantity($sku, $quantity);
    }

    /**
     * Update product stock quantity.
     *
     * @param string $sku
     * @param int $quantity
     */
    protected function updateProductQuantity($sku, int $quantity): void
    {
        try {
            if ($this->dataHelper->getDebug()) {
                $this->logger->info(__('Product %1 with the qty of %2 will be saved.', $sku, $quantity));
            }

            /** @var SourceItemInterface $ourceItem */
            $ourceItem = $this->sourceItemFactory->create();
            $ourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
            $ourceItem->setSku($sku);
            $ourceItem->setQuantity($quantity);
            $ourceItem->setSourceCode('YellowCube');
            $this->sourceItemSave->execute([$ourceItem]);
        } catch (\Exception $e) {
            $this->logger->critical($e);
        }
    }

    /**
     * @param $date
     * @return date
     */
    protected function convertYCDate($date)
    {
        return date('d.m.Y', strtotime($date));
    }
}
