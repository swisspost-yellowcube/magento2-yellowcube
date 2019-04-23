<?php

namespace Swisspost\YellowCube\Tests\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Swisspost\YellowCube\Model\Synchronizer;
use Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository;
use YellowCube\ART\Article;
use YellowCube\BAR\Inventory;
use YellowCube\BAR\QuantityUOM;

class ProductTest extends YellowCubeTestBase
{

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

        $inventory = new Inventory([$article1, $article2], date('YmdHi', strtotime('yesterday')));

        $this->yellowCubeServiceMock->expects($this->atLeastOnce())
            ->method('getInventoryWithMetadata')
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
        $inventory->setTimestamp(date('YmdHi', strtotime('tomorrow')));

        // Now the item is no longer removed from the inventory.
        $synchronizer = $this->_objectManager->get(Synchronizer::class);
        $synchronizer->bar();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);
        $this->assertStock('simple2', 8, 8);

    }
}
