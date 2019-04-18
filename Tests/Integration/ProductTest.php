<?php

namespace Swisspost\YellowCube\Tests\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\CustomerRepository;
use Magento\Inventory\Model\Stock;
use Magento\Inventory\Model\StockRepository;
use Magento\Inventory\Model\StockSourceLink;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\MysqlMq\Model\ResourceModel\Queue;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Shipment\Item;
use Magento\Store\Model\StoreManager;
use function strtotime;
use Swisspost\YellowCube\Model\Library\ClientFactory;
use Swisspost\YellowCube\Model\Synchronizer;
use Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository;
use YellowCube\ART\Article;
use YellowCube\BAR\QuantityUOM;
use YellowCube\Service;

class ProductTest extends \Magento\TestFramework\TestCase\AbstractController
{

    /**
     * The yellowcube service mock.
     *
     * @var \YellowCube\Service|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $yellowCubeServiceMock;

    /**
     * @var \Magento\MysqlMq\Model\ResourceModel\Queue
     */
    protected $queueModel;

    /**
     * @var \Magento\Framework\MessageQueue\ConsumerInterface
     */
    protected $queueConsumer;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * @var \Magento\TestFramework\ObjectManager
     */
    protected $_objectManager;

    /**
     * @var \Magento\InventoryApi\Api\GetSourceItemsBySkuInterface
     */
    protected $sourceItemsBySku;

    protected function setUp()
    {
        parent::setUp();

        $this->yellowCubeServiceMock = $this->getMockBuilder(Service::class)->disableOriginalConstructor()->getMock();

        $clientFactoryMock = $this->getMockBuilder(ClientFactory::class)->disableOriginalConstructor()->getMock();
        $clientFactoryMock->expects($this->any())
            ->method('getService')
            ->willReturn($this->yellowCubeServiceMock);
        $this->_objectManager->addSharedInstance($clientFactoryMock, ClientFactory::class);

        $this->queueModel = $this->_objectManager->get(Queue::class);

        /** @var \Magento\Framework\MessageQueue\ConsumerFactory $consumerFactory */
        $consumerFactory = $this->_objectManager->create(\Magento\Framework\MessageQueue\ConsumerFactory::class);
        $this->queueConsumer = $consumerFactory->get('yellowcube.sync');

        $this->productRepository = $this->_objectManager->get(ProductRepositoryInterface::class);

        $seachCriteriaBuilder = $this->_objectManager->get(\Magento\Framework\Api\SearchCriteriaBuilder::class);
        $searchCriteria = $seachCriteriaBuilder->addFilter('name', 'YellowCube')->create();
        /** @var \Magento\Inventory\Model\StockRepository $stockRepository */
        $stockRepository = $this->_objectManager->get(StockRepository::class);

        $stocks = $stockRepository->getList($searchCriteria)->getItems();
        $stock = reset($stocks);

        /** @var \Magento\InventorySalesApi\Api\Data\SalesChannelInterface $salesChannel */
        $salesChannel = $this->_objectManager->create(\Magento\InventorySalesApi\Api\Data\SalesChannelInterface::class);
        $salesChannel->setCode('base');
        $salesChannel->setType('website');
        $stock->getExtensionAttributes()->setSalesChannels([
            $salesChannel
        ]);
        /** @var \Magento\Inventory\Model\StockRepository $stockRepository */
        $stockRepository->save($stock);

        $this->sourceItemsBySku = $this->_objectManager->get(GetSourceItemsBySkuInterface::class);
    }

    public static function loadFixture()
    {
        include __DIR__ . '/../_files/config.php';
        include __DIR__ . '/../_files/products.php';
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testEnabling()
    {
        $product = $this->productRepository->get('simple1');
        $product->setData('yc_sync_with_yellowcube', true);
        $product->setData('yc_ean_type', 'HE');
        $product->setData('yc_ean_code', '135');
        $this->productRepository->save($product);

        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));

        $uom = \YellowCube\ART\UnitsOfMeasure\ISO::CMT;
        $article = new Article();
        $article
            ->setChangeFlag(\YellowCube\ART\ChangeFlag::INSERT)
            ->setPlantID('Y022')
            ->setDepositorNo('54321')
            ->setBaseUOM(\YellowCube\ART\UnitsOfMeasure\ISO::PCE)
            ->setAlternateUnitISO(\YellowCube\ART\UnitsOfMeasure\ISO::PCE)
            ->setArticleNo('simple1')
            ->setNetWeight(
                '.950',
                \YellowCube\ART\UnitsOfMeasure\ISO::KGM
            )
            ->setGrossWeight('1.000', \YellowCube\ART\UnitsOfMeasure\ISO::KGM)
            ->setLength('15.000', $uom)
            ->setWidth('10.000', $uom)
            ->setHeight('20.000', $uom)
            ->setVolume('3000.000', \YellowCube\ART\UnitsOfMeasure\ISO::CMQ)
            ->setEAN('135', 'HE')
            ->setBatchMngtReq(0)
            // @todo provide the language of the current description (possible values de|fr|it|en)
            ->addArticleDescription('Simple Product 1 with a very long title ', 'de');

        $this->yellowCubeServiceMock->expects($this->exactly(1))
            ->method('insertArticleMasterData')
            ->with($article);

        $this->queueConsumer->process(1);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testInventory()
    {
        $article1 = new \YellowCube\BAR\Article();
        $article1
            ->setArticleNo('simple1')
            ->setQuantityUOM(new QuantityUOM(17));

        $article2 = new \YellowCube\BAR\Article();
        $article2
            ->setArticleNo('simple2')
            ->setQuantityUOM(new QuantityUOM(9));

        $inventory = new \stdClass();
        $inventory->ControlReference = new \stdClass();
        $inventory->ControlReference->Timestamp = date('YmdHi', strtotime('yesterday'));
        $inventory->ArticleList = new \stdClass();
        $inventory->ArticleList->Article = [$article1, $article2];

        $this->yellowCubeServiceMock->expects($this->atLeastOnce())
            ->method('getInventoryWithControlReference')
            ->willReturn($inventory);

        $synchronizer = $this->_objectManager->get(Synchronizer::class);
        $synchronizer->bar();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        $this->assertStock('simple1', 17, 17);
        $this->assertStock('simple2', 9, 9);

        // Now simulate an order with a shipment item that will be
        $order = $this->createOrder([
            'items' => [
                [
                    'product_id' => 2,
                    'qty' => 3,
                ]
            ],
        ]);

        // Create a shipment item for simple2 to have the count subtracted.
        /** @var \Magento\Sales\Model\Order\ShipmentRepository $shipmentRepository */
        $shipmentRepository = $this->_objectManager->get(Order\ShipmentRepository::class);
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $this->_objectManager->get(Shipment::class);

        /** @var \Magento\Sales\Model\Order\Shipment\Item $shipmentItem */
        $shipmentItem = $this->_objectManager->get(\Magento\Sales\Model\Order\Shipment\Item::class);
        $shipmentItem->setProductId(2);
        $shipmentItem->setQty(3);
        $order_items = $order->getItems();
        $shipmentItem->setOrderItem(reset($order_items));

        $shipment->addItem($shipmentItem);
        $shipment->setOrder($order);
        $shipment->getExtensionAttributes()->setSourceCode('YellowCube');
        $shipmentRepository->save($shipment);

        /** @var \Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository $yellowCubeShipmentItemRepository */
        $yellowCubeShipmentItemRepository = $this->_objectManager->get(\Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository::class);

        // Manually create the shipment item yellowcube status information.
        $yellowCubeShipmentItemRepository->insertShipmentItem($shipmentItem, 1245);

        // Saving the shipment automatically deducts the source.
        $this->assertStock('simple2', 9, 6);

        // Ensure that synchronizing again keeps the stock.
        $synchronizer = $this->_objectManager->get(Synchronizer::class);
        $synchronizer->bar();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);
        $this->assertStock('simple2', 9, 6);

        // Test with a shipment item in status confirmed.
        $yellowCubeShipmentItemRepository->updateByShipmentId($shipmentItem->getEntityId(), YellowCubeShipmentItemRepository::STATUS_CONFIRMED);

        // Ensure that synchronizing again keeps the stock.
        $synchronizer = $this->_objectManager->get(Synchronizer::class);
        $synchronizer->bar();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);
        $this->assertStock('simple2', 9, 6);

        // Test with a shipment item in status shipped and a new article count.
        $article2->setQuantityUOM(new QuantityUOM(8));
        $yellowCubeShipmentItemRepository->updateByShipmentId($shipmentItem->getEntityId(), YellowCubeShipmentItemRepository::STATUS_SHIPPED);

        // The item was shipped before the inventory, so it is still removed.
        $synchronizer = $this->_objectManager->get(Synchronizer::class);
        $synchronizer->bar();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);
        $this->assertStock('simple2', 8, 5);

        // We can not fake the shipment item timestamp, so fake the inventory timestamp instead, set it to the past
        // now the shipped item should still be removed.

        $inventory->ControlReference->Timestamp = date('YmdHi', strtotime('tomorrow'));

        // Now the item is no longer removed from the inventory.
        $synchronizer = $this->_objectManager->get(Synchronizer::class);
        $synchronizer->bar();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);
        $this->assertStock('simple2', 8, 8);

    }

    /**
     * Create an order
     *
     * @param array $orderInfo
     *
     * @return \Magento\Framework\Model\AbstractExtensibleModel|\Magento\Sales\Api\Data\OrderInterface|object|null
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function createOrder($orderInfo)
    {
        $orderInfo += [
            'email' => 'customer@example.org',
            'address' => [
                'firstname' => 'John',
                'lastname' => 'Example','prefix' => '',
                'suffix' => '',
                'street' => 'Hermetschloostrasse 77',
                'city' => 'Zürich',
                'country_id' => 'CH',
                'region' => 'Zürich',
                'region_id' => '129',
                'postcode' => '8048',
                'telephone' => '1234512345'
            ]
        ];

        /** @var \Magento\Store\Model\StoreManager $storeManager */
        $storeManager = $this->_objectManager->get(StoreManager::class);

        /** @var \Magento\Customer\Api\CustomerRepositoryInterface $customerRepository */
        $customerRepository = $this->_objectManager->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);

        /** @var \Magento\Quote\Model\QuoteFactory $quoteFactory */
        $quoteFactory = $this->_objectManager->get(\Magento\Quote\Model\QuoteFactory::class);

        /** @var \Magento\Quote\Model\QuoteManagement $quoteManagement */
        $quoteManagement = $this->_objectManager->get(\Magento\Quote\Model\QuoteManagement::class);

        $store = $storeManager->getStore();
        $websiteId = $storeManager->getStore()->getWebsiteId();
        $customerFactory = $this->_objectManager->get(\Magento\Customer\Model\CustomerFactory::class);
        $customer = $customerFactory->create();
        $customer->setWebsiteId($websiteId)
            ->setStore($store)
            ->setFirstname($orderInfo['address']['firstname'])
            ->setLastname($orderInfo['address']['lastname'])
            ->setEmail($orderInfo['email']);
        $customer->save();
        $customer = $customerRepository->getById($customer->getId());

        $quote = $quoteFactory->create();
        $quote->setStore($store);
        $quote->setCurrency();
        $quote->assignCustomer($customer);

        //add items in quote
        foreach ($orderInfo['items'] as $item) {
            $product = $this->productRepository->getById($item['product_id']);
            $quote->addProduct($product, intval($item['qty']));
        }

        //Set Billing and shipping Address to quote
        $quote->getBillingAddress()->addData($orderInfo['address']);
        $quote->getShippingAddress()->addData($orderInfo['address']);

        // set shipping method
        $shippingAddress = $quote->getShippingAddress();
        $shippingAddress->setCollectShippingRates(true)
            ->collectShippingRates()
            ->setShippingMethod('yellowcube_ECO');
        $quote->setPaymentMethod('checkmo');
        $quote->setInventoryProcessed(false);
        $quote->save();

        // Set Sales Order Payment, We have taken check/money order
        $quote->getPayment()->importData(['method' => 'checkmo']);

        // Collect Quote Totals & Save
        $quote->collectTotals()->save();
        // Create Order From Quote Object
        $order = $quoteManagement->submit($quote);
        $order->setIsInProgress(true);
        $order->save();
        return $order;
    }

    /**
     * @param string $product_sku
     * @param int $yellowcube_stock
     * @param int $magento_stock
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    protected function assertStock(string $product_sku, int $yellowcube_stock, int $magento_stock)
    {
        // @todo the product repository seems to have multiple instances that can get out of sync, ensure we have
        //   a fresh instance.
        $this->productRepository = $this->_objectManager->create(ProductRepositoryInterface::class);

        $product = $this->productRepository->get($product_sku);
        $this->assertEquals($yellowcube_stock, $product->getData('yc_stock'));
        $sourceItems = $this->sourceItemsBySku->execute($product_sku);
        $quantity_by_source = [];
        foreach ($sourceItems as $sourceItem) {
            $quantity_by_source[$sourceItem->getSourceCode()] = $sourceItem->getQuantity();
        }
        $this->assertEquals(['default' => 0, 'YellowCube' => $magento_stock], $quantity_by_source);
    }
}
