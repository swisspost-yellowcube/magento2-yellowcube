<?php

namespace Swisspost\YellowCube\Tests\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Inventory\Model\StockRepository;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\MysqlMq\Model\ResourceModel\Queue;
use Magento\Store\Model\StoreManager;
use Swisspost\YellowCube\Model\Library\ClientFactory;
use YellowCube\Service;

class YellowCubeTestBase extends \Magento\TestFramework\TestCase\AbstractController
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

    /**
     * Create an order
     *
     * @param array $orderInfo
     *
     * @return \Magento\Framework\Model\AbstractExtensibleModel|\Magento\Sales\Api\Data\OrderInterface|object|null
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
            ],
            'in_progress' => true,
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

        $customer = null;
        try {
            $customer = $customerRepository->get($orderInfo['email']);
        } catch (NoSuchEntityException $e) {
            $customer = $customerFactory->create();
            $customer->setWebsiteId($websiteId)
                ->setStore($store)
                ->setFirstname($orderInfo['address']['firstname'])
                ->setLastname($orderInfo['address']['lastname'])
                ->setEmail($orderInfo['email']);
            $customer->save();
            $customer = $customerRepository->get($orderInfo['email']);
        }

        $quote = $quoteFactory->create();
        $quote->setStore($store);
        $quote->setCurrency();
        $quote->assignCustomer($customer);

        //add items in quote
        foreach ($orderInfo['items'] as $item) {
            $product = $this->productRepository->get($item['product_sku']);
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
        $order->setIsInProcess($orderInfo['in_progress']);
        $order->save();
        return $order;
    }

    /**
     * @param string $product_sku
     * @param int $yellowcube_stock
     * @param int $magento_stock
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
