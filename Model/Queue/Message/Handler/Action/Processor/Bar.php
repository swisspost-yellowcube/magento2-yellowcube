<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor;

use Swisspost\YellowCube\Helper\Data;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;

class Bar
    extends ProcessorAbstract
    implements ProcessorInterface
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
    protected $salesResourceModelOrderShipmentItemCollectionFactory;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Swisspost\YellowCube\Helper\Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Catalog\Model\ResourceModel\Product\ActionFactory $catalogResourceModelProductActionFactory,
        \Magento\Sales\Model\ResourceModel\Order\Shipment\Item\CollectionFactory $salesResourceModelOrderShipmentItemCollectionFactory
    ) {
        parent::__construct($logger, $dataHelper, $clientFactory);
        $this->catalogProductFactory = $catalogProductFactory;
        $this->storeManager = $storeManager;
        $this->catalogResourceModelProductActionFactory = $catalogResourceModelProductActionFactory;
        $this->salesResourceModelOrderShipmentItemCollectionFactory = $salesResourceModelOrderShipmentItemCollectionFactory;
    }

    /**
     * @param array $data
     * @return $this
     */
    public function process(array $data)
    {
        $stockItems = $this->getYellowCubeService()->getInventory();

        $this->logger->info(__('YellowCube reports %d products with a stock level', count($stockItems)));

        /* @var $article \YellowCube\BAR\Article */
        foreach ($stockItems as $article) {
            $articleNo = $article->getArticleNo();
            $articleLot = $article->getLot();

            //todo @psa make sure this gives NULL if empty
            if (!is_null($article->getLot())) {
                $lotSummary[$articleNo]['qty'] = $article->getQuantityUOM()->get();
                $lotSummary[$articleNo]['lotInfo'] = 'Lot: ' . $articleLot . " Quantity: " . (int)$article->getQuantityUOM()->get() . ' ExpDate: ' . $this->convertYCDate($article->getBestBeforeDate()) . PHP_EOL;
                $lotSummary[$articleNo]['recentExpDate'] = $article->getBestBeforeDate();

                foreach ($stockItems as $article2) {
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

        foreach ($lotSummary as $articleNo => $articleData) {
            //todo do the update here
            $this->update($articleNo, $articleData);
        }

        $this->logger->info(print_r($lotSummary, true));

        return $this;
    }

    /**
     * @param $productId
     * @param $data
     * @return $this
     */
    public function update($productId, $data)
    {
        /** @var $product Mage_Catalog_Model_Product */
        $product = $this->catalogProductFactory->create();
        $idBySku = $product->getIdBySku($productId);
        $productId = $idBySku ? $idBySku : $productId;

        $product
            ->setStoreId($this->storeManager->getStore(0)->getId())
            ->load($productId);

        if (!$product->getId()) {
            $this->logger->log(\Monolog\Logger::INFO,
                __('Product %s inventory cannot be synchronized from YellowCube into Magento because it does not exist.',
                    $productId));
            return $this;
        }

        /**
         * YellowCube lot - Handle the Lot information for the product
         */
        if (!is_null($data['recentExpDate'])) //only do lot info if there is lot info available
        {
            $action = $this->catalogResourceModelProductActionFactory->create();
            $action->updateAttributes(array($productId), array(
                'yc_lot_info' => $data['lotInfo'],
                'yc_most_recent_expiration_date' => $this->convertYCDate($data['recentExpDate'])
            ), $this->storeManager->getStore(0)->getId());
        }

        /**
         * YellowCube stock - qty of products not yet shipped = new stock
         */
        $shipmentItemsCollection = $this->salesResourceModelOrderShipmentItemCollectionFactory->create();
        $shipmentItemsCollection
            ->addFieldToFilter('product_id', $product->getId())
            ->addFieldToSelect('additional_data')
            ->addFieldToSelect('qty');

        $qtyToDecrease = 0;
        foreach ($shipmentItemsCollection->getItems() as $shipment) {
            $additionalData = \Zend_Json::decode($shipment->getAdditionalData());
            if (isset($additionalData['yc_shipped']) && $additionalData['yc_shipped'] === 0) {
                $qtyToDecrease += $shipment->getQty();
            } else {
                continue;
            }
        }

        $data['qty'] -= $qtyToDecrease;

        /** @var $stockItem Mage_CatalogInventory_Model_Stock_Item */
        $stockItem = $product->getStockItem();
        $stockData = array_replace($stockItem->getData(), (array)$data);
        $stockItem->setData($stockData);

        try {
            if ($this->getHelper()->getDebug()) {
                $this->logger->log(\Monolog\Logger::INFO,
                    __('Product %s with the qty of %s will be saved..', $productId, $stockItem->getQty()));
            }
            $stockItem->save();
        } catch (Exception $e) {
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
