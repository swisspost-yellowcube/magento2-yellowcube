<?php

namespace Swisspost\YellowCube\Tests\Integration;

use Magento\Framework\Registry;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Swisspost\YellowCube\Model\ArticleResponseSync;
use Swisspost\YellowCube\Model\Synchronizer;
use Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository;
use Swisspost\YellowCube\Model\YellowCubeStock;
use YellowCube\ART\Article;
use YellowCube\BAR\Inventory;
use YellowCube\BAR\QuantityUOM;
use YellowCube\GEN_Response;

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
    public function testEnablingSuccess()
    {
        $product = $this->productRepository->get('simple1');
        $product->setData('yc_sync_with_yellowcube', '0');
        $this->productRepository->save($product);
        $this->assertCount(0, $this->queueModel->getMessages('yellowcube.sync'));

        $product = $this->productRepository->get('simple1');
        $product->setData('yc_sync_with_yellowcube', true);
        $product->setData('yc_ean_type', 'HE');
        $product->setData('yc_ean_code', '135');
        $this->productRepository->save($product);

        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));

        $response = $this->createMock(GEN_Response::class);
        $response->expects($this->any())
            ->method('isSuccess')
            ->willReturn(true);
        $response->expects($this->any())
            ->method('getReference')
            ->willReturn(23456);

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
            ->addArticleDescription('Simple Product 1 with a very long title ', 'de');

        $article_delete = clone $article;
        $article_delete->setChangeFlag(\YellowCube\ART\ChangeFlag::DEACTIVATE);

        $this->yellowCubeServiceMock->expects($this->exactly(2))
            ->method('insertArticleMasterData')
            ->withConsecutive($article, $article_delete)
            ->willReturn($response);

        $this->queueConsumer->process(1);

        $product = $this->reloadProduct($product->getSku());
        $this->assertEquals(23456, $product->getData('yc_reference'));

        // Check the response, first a pending response, then a success.
        $pending_response = $this->createMock(GEN_Response::class);
        $pending_response->expects($this->atLeastOnce())
            ->method('isPending')
            ->willReturn(true);

        $response = $this->createMock(GEN_Response::class);
        $response->expects($this->atLeastOnce())
            ->method('isError')
            ->willReturn(false);
        $response->expects($this->atLeastOnce())
            ->method('isPending')
            ->willReturn(false);

        $this->yellowCubeServiceMock->expects($this->exactly(2))
            ->method('getInsertArticleMasterDataStatus')
            ->with(23456)
            ->willReturnOnConsecutiveCalls($pending_response, $response);

        /** @var \Swisspost\YellowCube\Model\ArticleResponseSync $article_response_sync */
        $article_response_sync = $this->_objectManager->get(ArticleResponseSync::class);
        $article_response_sync->processArticles();

        $product = $this->reloadProduct($product->getSku());
        $this->assertEquals(23456, $product->getData('yc_reference'));

        $article_response_sync->processArticles();

        $product = $this->reloadProduct($product->getSku());
        $this->assertNull($product->getData('yc_reference'));

        // Syncing again should not result in a third call to getInsertArticleMasterDataStatus().
        $article_response_sync->processArticles();

        // Delete the product.
        $registry = $this->_objectManager->get(Registry::class);

        $registry->register('isSecureArea', true);

        $this->productRepository->delete($product);
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testEnablingError()
    {
        $product = $this->productRepository->get('simple1');
        $product->setData('yc_sync_with_yellowcube', true);
        $product->setData('yc_ean_type', 'HE');
        $product->setData('yc_ean_code', '135');
        $this->productRepository->save($product);

        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));

        $response = $this->createMock(GEN_Response::class);
        $response->expects($this->any())
            ->method('isSuccess')
            ->willReturn(true);
        $response->expects($this->any())
            ->method('getReference')
            ->willReturn(23456);

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
            ->addArticleDescription('Simple Product 1 with a very long title ', 'de');

        $article_delete = clone $article;
        $article_delete->setChangeFlag(\YellowCube\ART\ChangeFlag::DEACTIVATE);

        $this->yellowCubeServiceMock->expects($this->exactly(1))
            ->method('insertArticleMasterData')
            ->withConsecutive($article, $article_delete)
            ->willReturn($response);

        $this->queueConsumer->process(1);

        $product = $this->reloadProduct($product->getSku());
        $this->assertEquals(23456, $product->getData('yc_reference'));

        // Return an error response.
        $response = $this->createMock(GEN_Response::class);
        $response->expects($this->atLeastOnce())
            ->method('isError')
            ->willReturn(true);
        $response->expects($this->atLeastOnce())
            ->method('isPending')
            ->willReturn(false);
        $response->expects($this->atLeastOnce())
            ->method('getStatusText')
            ->willReturn('Not accepted because article is too big.');

        $this->yellowCubeServiceMock->expects($this->once())
            ->method('getInsertArticleMasterDataStatus')
            ->with(23456)
            ->willReturn($response);

        /** @var \Swisspost\YellowCube\Model\ArticleResponseSync $article_response_sync */
        $article_response_sync = $this->_objectManager->get(ArticleResponseSync::class);
        $article_response_sync->processArticles();

        $product = $this->reloadProduct($product->getSku());
        $this->assertNull($product->getData('yc_reference'));
        $this->assertEquals('Not accepted because article is too big.', $product->getData('yc_response'));
        $this->assertEquals(0, $product->getData('yc_sync_with_yellowcube'));
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testEnablingWithLot()
    {
        $product = $this->productRepository->get('simple1');
        $product->setData('yc_sync_with_yellowcube', true);
        $product->setData('yc_ean_type', 'HE');
        $product->setData('yc_ean_code', '135');
        $product->setData('yc_requires_lot_management', true);
        $this->productRepository->save($product);

        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));

        $response = $this->createMock(GEN_Response::class);
        $response->expects($this->any())
            ->method('isSuccess')
            ->willReturn(true);

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
            ->setBatchMngtReq(1)
            ->addArticleDescription('Simple Product 1 with a very long title ', 'de');

        $article_disable = clone $article;
        $article_disable->setChangeFlag(\YellowCube\ART\ChangeFlag::DEACTIVATE);

        $this->yellowCubeServiceMock->expects($this->exactly(2))
            ->method('insertArticleMasterData')
            ->withConsecutive($article, $article_disable)
            ->willReturn($response);

        $this->queueConsumer->process(1);

        // Disable the product.
        $product = $this->productRepository->getById($product->getId());
        $product->setData('yc_sync_with_yellowcube', false);
        $this->productRepository->save($product);
        $this->queueConsumer->process(1);

        $product = $this->productRepository->getById($product->getId());
        $this->assertEquals(0, $product->getData('yc_requires_lot_management'));
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testEnablingChanges()
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

        $this->yellowCubeServiceMock->expects($this->exactly(3))
            ->method('insertArticleMasterData')
            ->willReturn($response);

        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        $attributes = ['name', 'weight', 'ts_dimensions_length', 'ts_dimensions_width', 'ts_dimensions_height', 'ts_dimensions_uom', 'yc_ean_type', 'yc_ean_code'];

        // Enable lot management.
        $product = $this->productRepository->get('simple1');
        $product->setData('yc_requires_lot_management', true);
        $this->productRepository->save($product);

        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        // Change name.
        $product = $this->productRepository->get('simple1');
        $product->setName('New product name');
        $this->productRepository->save($product);

        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        // No change.
        $product = $this->productRepository->get('simple1');
        $this->productRepository->save($product);

        $this->assertCount(0, $this->queueModel->getMessages('yellowcube.sync'));

        // Irrelevant change.
        $product = $this->productRepository->get('simple1');
        $product->setPrice(5);
        $this->productRepository->save($product);

        $this->assertCount(0, $this->queueModel->getMessages('yellowcube.sync'));
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testInventory()
    {

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


        $product = $this->_objectManager->create('Magento\Catalog\Model\Product');
        $product
            ->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
            ->setAttributeSetId(4)
            ->setWebsiteIds([1])
            ->setName('Simple Product 3')
            ->setSku('simple3')
            ->setPrice(15)
            ->setWeight(2)
            ->setData('is_salable', 1)
            ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
            ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
            ->setData('ts_dimensions_length', 7)
            ->setData('ts_dimensions_width', 3)
            ->setData('ts_dimensions_height', 9)
            ->setData('yc_sync_with_yellowcube', true)
            ->save();

        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        $one_week = strtotime('1 week');
        $one_month = strtotime('1 month');

        $article1 = new \YellowCube\BAR\Article();
        $article1
            ->setArticleNo('simple1')
            ->setYcLot('A')
            ->setYCArticleNo('Y123456')
            ->setBestBeforeDate(date('YmdHi', $one_week))
            ->setQuantityUOM(new QuantityUOM(17));

        $article2 = new \YellowCube\BAR\Article();
        $article2
            ->setArticleNo('simple2')
            ->setYCArticleNo('Y123457')
            ->setQuantityUOM(new QuantityUOM(9));

        $article11 = new \YellowCube\BAR\Article();
        $article11
            ->setArticleNo('simple1')
            ->setYcLot('B')
            ->setYCArticleNo('Y123458')
            ->setBestBeforeDate(date('YmdHi', $one_month))
            ->setQuantityUOM(new QuantityUOM(19));

        $inventory = new Inventory([$article1, $article2, $article11], date('YmdHi', strtotime('yesterday')));

        $this->yellowCubeServiceMock->expects($this->atLeastOnce())
            ->method('getInventoryWithMetadata')
            ->willReturn($inventory);

        $synchronizer = $this->_objectManager->get(Synchronizer::class);
        $synchronizer->bar();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);

        $this->assertStock('simple1', 36, 36);
        $this->assertStock('simple2', 9, 9);
        $this->assertStock('simple3', 0, 0);

        // Assert the YellowCube stock.
        /** @var \Swisspost\YellowCube\Model\YellowCubeStock $yellowCubeStock */
        $yellowCubeStock = $this->_objectManager->get(YellowCubeStock::class);
        $stock = $yellowCubeStock->getStock();
        $this->assertEquals([
            [
                'sku' => 'simple1',
                'lot' => 'A',
                'quantity' => '17',
                'best_before_date' => date('Y-m-d', $one_week),
                'yc_article_no' => 'Y123456'
            ],
            [
                'sku' => 'simple2',
                'lot' => null,
                'quantity' => '9',
                'best_before_date' => null,
                'yc_article_no' => 'Y123457'
            ],
            [
                'sku' => 'simple1',
                'lot' => 'B',
                'quantity' => '19',
                'best_before_date' => date('Y-m-d', $one_month),
                'yc_article_no' => 'Y123458'
            ],
        ], $stock);

        // Now simulate an order with a shipment item that will be
        $order = $this->createOrder([
            'items' => [
                [
                    'product_sku' => 'simple2',
                    'qty' => 3,
                ],
                [
                    'product_sku' => 'simple1',
                    'qty' => 5,
                ],
            ],
            'in_progress' => false,
        ]);

        // Create a shipment item for simple2 to have the count subtracted.
        /** @var \Magento\Sales\Model\Order\ShipmentRepository $shipmentRepository */
        $shipmentRepository = $this->_objectManager->get(Order\ShipmentRepository::class);
        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $this->_objectManager->get(Shipment::class);

        /** @var \Magento\Sales\Model\Order\Shipment\Item $shipmentItem */
        $shipmentItem = $this->_objectManager->create(\Magento\Sales\Model\Order\Shipment\Item::class);
        $shipmentItem->setProductId($this->productRepository->get('simple2')->getId());
        $shipmentItem->setQty(3);
        $order_items = $order->getItems();
        list($order_item1, $order_item2) = $order_items;
        $shipmentItem->setOrderItem($order_item1);

        $shipment->addItem($shipmentItem);
        $shipment->setOrder($order);
        $shipment->getExtensionAttributes()->setSourceCode('YellowCube');

        // Add a second shipment item for the second product.
        $shipmentItem2 = $this->_objectManager->create(\Magento\Sales\Model\Order\Shipment\Item::class);
        $shipmentItem2->setProductId($this->productRepository->get('simple1')->getId());
        $shipmentItem2->setQty(5);
        $shipmentItem2->setOrderItem($order_item2);

        $shipment->addItem($shipmentItem2);

        $shipmentRepository->save($shipment);

        /** @var \Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository $yellowCubeShipmentItemRepository */
        $yellowCubeShipmentItemRepository = $this->_objectManager->get(\Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository::class);

        // Manually create the shipment item yellowcube status information.
        $yellowCubeShipmentItemRepository->insertShipmentItem($shipmentItem, 1245);
        $yellowCubeShipmentItemRepository->insertShipmentItem($shipmentItem2, 1245);

        // Saving the shipment automatically deducts the source.
        $this->assertStock('simple2', 9, 6);

        // Ensure that synchronizing again keeps the stock.
        $synchronizer = $this->_objectManager->get(Synchronizer::class);
        $synchronizer->bar();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);
        $this->assertStock('simple2', 9, 6);

        // Test with a shipment item in status confirmed.
        $yellowCubeShipmentItemRepository->updateByShipmentId($shipmentItem->getParentId(), YellowCubeShipmentItemRepository::STATUS_CONFIRMED);

        // Ensure that synchronizing again keeps the stock.
        $synchronizer = $this->_objectManager->get(Synchronizer::class);
        $synchronizer->bar();
        $this->assertCount(1, $this->queueModel->getMessages('yellowcube.sync'));
        $this->queueConsumer->process(1);
        $this->assertStock('simple2', 9, 6);

        // Test with a shipment item in status shipped and a new article count.
        $article2->setQuantityUOM(new QuantityUOM(8));
        $yellowCubeShipmentItemRepository->updateByShipmentId($shipmentItem->getParentId(), YellowCubeShipmentItemRepository::STATUS_SHIPPED);

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
