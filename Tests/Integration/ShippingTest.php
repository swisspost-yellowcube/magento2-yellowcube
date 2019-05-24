<?php

namespace Swisspost\YellowCube\Tests\Integration;

use function array_values;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Swisspost\YellowCube\Helper\Data;
use Swisspost\YellowCube\Model\ShipmentStatusSync;
use Swisspost\YellowCube\Model\Shipping\Carrier\Carrier;
use Swisspost\YellowCube\Model\Synchronizer;
use Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository;
use YellowCube\GEN_Response;
use YellowCube\WAB\AdditionalService\AdditionalShippingServices;
use YellowCube\WAB\AdditionalService\BasicShippingServices;
use YellowCube\WAB\OrderHeader;
use YellowCube\WAB\Partner;
use YellowCube\WAB\Position;
use YellowCube\WAR\GoodsIssue\CustomerOrderDetail;
use YellowCube\WAR\GoodsIssue\CustomerOrderHeader;
use YellowCube\WAR\GoodsIssue\GoodsIssue;

class ShippingTest extends YellowCubeTestBase
{
    /**
     * @var \Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository
     */
    protected $yellowCubeShipmentItemRepository;

    public function setUp()
    {
        parent::setUp();

        $this->yellowCubeShipmentItemRepository = $this->_objectManager->get(\Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository::class);
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
    public function testShippingSuccess()
    {
        /** @var ShipmentInterface $shipment */
        list($shipment, $shipmentItem) = $this->createOrderAndShip();

        $shipment = $this->reloadShipment($shipment);
        $this->assertEquals(Carrier::STATUS_SUBMITTED, $shipment->getShipmentStatus());

        $response = new \YellowCube\GEN_Response(date('YmdHi', strtotime('now')), 'test', 100, 'S', 'Status text', '9999');
        $this->yellowCubeServiceMock->expects($this->once())
            ->method('getYCCustomerOrderStatus')
            ->with('9999')
            ->willReturn($response);

        // Check for the status.
        /** @var \Swisspost\YellowCube\Model\ShipmentStatusSync $shipmentStatusSync */
        $shipmentStatusSync = $this->_objectManager->get(ShipmentStatusSync::class);
        $shipmentStatusSync->processPendingShipments();

        $shipments = $this->yellowCubeShipmentItemRepository->getByReference('9999');
        $this->assertCount(1, $shipments);
        $this->assertEquals($shipment->getId(), $shipments[$shipmentItem->getId()]['shipment_id']);
        $this->assertEquals(YellowCubeShipmentItemRepository::STATUS_CONFIRMED, $shipments[$shipmentItem->getId()]['status']);

        $shipment = $this->reloadShipment($shipment);
        $this->assertEquals(Carrier::STATUS_CONFIRMED, $shipment->getShipmentStatus());

        /** @var \Swisspost\YellowCube\Model\Synchronizer $synchronizer */
        $synchronizer = $this->_objectManager->get(Synchronizer::class);

        // Check for shipping confirmation, first time nothing happened yet, on the second time, return data.
        $goodsInfo = new GoodsIssue();
        $goodsInfo->setCustomerOrderHeader(new CustomerOrderHeader(123, '', $shipment->getIncrementId(), null, 7777));

        $orderDetail = new CustomerOrderDetail(null, null, null, 'simple1');

        $goodsInfo->setCustomerOrderList([$orderDetail]);

        $this->yellowCubeServiceMock->expects($this->exactly(2))
            ->method('getYCCustomerOrderReply')
            ->willReturnOnConsecutiveCalls([], [$goodsInfo]);

        $synchronizer->war();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        $synchronizer->war();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        $shipment = $this->reloadShipment($shipment);
        $this->assertEquals(Carrier::STATUS_SHIPPED, $shipment->getShipmentStatus());

        $tracks = $shipment->getTracks();
        $this->assertCount(1, $tracks);
        $track = reset($tracks);
        $this->assertEquals('http://www.post.ch/swisspost-tracking?formattedParcelCodes=7777', $track->getNumber());

        $comments = $shipment->getComments();
        $this->assertCount(3, $comments);

        list($sentComment, $confirmedComment, $shippedComment) = array_values($comments);
        $this->assertEquals('Shipment #' . $shipment->getIncrementId() . ' for Order #' . $shipment->getOrder()->getIncrementId() . ' was successfully transmitted to YellowCube. Received reference number 9999 and status message "Status text".', $sentComment->getComment());
        $this->assertEquals('YellowCube Success Status text', $confirmedComment->getComment());
        $this->assertContains('Your order has been shipped. You can use the following url for shipping tracking: <a href="http://www.post.ch/swisspost-tracking?formattedParcelCodes=7777" target="_blank">http://www.post.ch/swisspost-tracking?formattedParcelCodes=7777</a>', $shippedComment->getComment());

        // Call it again, but there are no confirmed shipments anymore, so the total count of calls remains 2.
        $synchronizer->war();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        $this->assertStock('simple1', 0, 7);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testShippingError()
    {
        list($shipment, $shipmentItem) = $this->createOrderAndShip();

        $this->yellowCubeServiceMock->expects($this->once())
            ->method('getYCCustomerOrderStatus')
            ->with('9999')
            ->willThrowException(new \Exception('Product can not be shipped'));

        // Check for the status.
        /** @var \Swisspost\YellowCube\Model\ShipmentStatusSync $shipmentStatusSync */
        $shipmentStatusSync = $this->_objectManager->get(ShipmentStatusSync::class);
        $shipmentStatusSync->processPendingShipments();

        $shipments = $this->yellowCubeShipmentItemRepository->getByReference('9999');
        $this->assertCount(1, $shipments);
        $this->assertEquals($shipment->getId(), $shipments[$shipmentItem->getId()]['shipment_id']);
        $this->assertEquals(YellowCubeShipmentItemRepository::STATUS_ERROR, $shipments[$shipmentItem->getId()]['status']);
        $this->assertEquals('Product can not be shipped', $shipments[$shipmentItem->getId()]['message']);

        $shipment = $this->reloadShipment($shipment);
        $this->assertEquals(Carrier::STATUS_ERROR, $shipment->getShipmentStatus());

        $this->assertStock('simple1', 0, 7);
    }

    /**
     * @return array
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\StateException
     */
    protected function createOrderAndShip()
    {
        $product = $this->productRepository->get('simple1');
        $product->setData('yc_sync_with_yellowcube', true);
        $product->setData('yc_ean_type', 'HE');
        $product->setData('yc_ean_code', '135');
        $this->productRepository->save($product);

        $response = $this->createMock(GEN_Response::class);
        $response->expects($this->any())
            ->method('isSuccess')
            ->willReturn(true);
        $response->expects($this->any())
            ->method('getReference')
            ->willReturn(23456);

        $this->yellowCubeServiceMock->expects($this->any())
            ->method('insertArticleMasterData')
            ->willReturn($response);

        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        /** @var SourceItemInterface $sourceItem */
        $sourceItem = $this->_objectManager->create(SourceItemInterface::class);
        $sourceItem->setStatus(SourceItemInterface::STATUS_IN_STOCK);
        $sourceItem->setSku('simple1');
        $sourceItem->setQuantity(10);
        $sourceItem->setSourceCode('YellowCube');
        $sourceItemSave = $this->_objectManager->create(SourceItemsSaveInterface::class);
        $sourceItemSave->execute([$sourceItem]);

        $this->assertStock('simple1', 0, 10);

        // Now simulate an order with a shipment item that will be
        $order = $this->createOrder([
            'items' => [
                [
                    'product_sku' => 'simple1',
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
        $shipmentItem->setProductId($product->getId());
        $shipmentItem->setQty(3);
        $shipmentItem->setSku($product->getSku());
        $order_items = $order->getItems();
        $shipmentItem->setOrderItem(reset($order_items));

        $shipment->addItem($shipmentItem);
        $shipment->setOrder($order);
        $shipment->getExtensionAttributes()->setSourceCode('YellowCube');
        $shipmentRepository->save($shipment);

        $wab_order = new \YellowCube\WAB\Order();
        $wab_order->setOrderHeader(new OrderHeader('54321', $order->getIncrementId(), date('Ymd')));
        $partner = new Partner();
        $partner
            ->setPartnerType(Data::PARTNER_TYPE)
            ->setPartnerNo('0004653')
            ->setPartnerReference('JE-8048')
            ->setName1('John Example')
            ->setName2('')
            ->setName3('')
            ->setZIPCode('8048')
            ->setCountryCode('CH')
            ->setStreet('Hermetschloostrasse 77')
            ->setCity('Zürich')
            ->setPhoneNo('1234512345')
            ->setEmail('customer@example.org')
            ->setLanguageCode('en');

        $wab_order
            ->setPartnerAddress($partner)
            ->addValueAddedService(new BasicShippingServices('ECO'))
            ->addValueAddedService(new AdditionalShippingServices('NONE'));

        $order_position = new Position();
        $order_position
            ->setPosNo(1)
            ->setArticleNo('simple1')
            ->setEAN('135')
            ->setPlant('Y022')
            ->setQuantity('3')
            ->setQuantityISO(\YellowCube\ART\UnitsOfMeasure\ISO::PCE)
            ->setShortDescription('');

        $wab_order->addOrderPosition($order_position);

        $response = new \YellowCube\GEN_Response(
            date('YmdHi', strtotime('now')),
            'test',
            100,
            'S',
            'Status text',
            '9999'
        );

        $this->yellowCubeServiceMock->expects($this->once())
            ->method('createYCCustomerOrder')
            ->with($wab_order)
            ->willReturn($response);

        // @todo Workaround for https://github.com/magento-engcom/msi/issues/2276.
        $shipment->setOrigData('entity_id', $shipment->getEntityId());

        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        $shipments = $this->yellowCubeShipmentItemRepository->getByReference('9999');
        $this->assertCount(1, $shipments);
        $this->assertEquals($shipment->getId(), $shipments[$shipmentItem->getId()]['shipment_id']);
        $this->assertEquals(
            YellowCubeShipmentItemRepository::STATUS_SENT,
            $shipments[$shipmentItem->getId()]['status']
        );

        // Saving the shipment automatically deducts the source.
        $this->assertStock('simple1', 0, 7);

        // The order is no longer active.
        $order->setIsInProcess(false);
        $order->save();

        return [$shipment, $shipmentItem];
    }

    /**
     * @param ShipmentInterface $shipment
     * @return ShipmentInterface
     */
    protected function reloadShipment(ShipmentInterface $shipment): ShipmentInterface
    {
        /** @var \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository */
        $shipmentRepository = $this->_objectManager->create(ShipmentRepositoryInterface::class);
        $shipment = $shipmentRepository->get($shipment->getEntityId());
        return $shipment;
    }
}
