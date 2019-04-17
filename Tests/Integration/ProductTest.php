<?php

namespace Swisspost\YellowCube\Tests\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\MysqlMq\Model\ResourceModel\Queue;
use YellowCube\ART\Article;
use YellowCube\Service;
use Swisspost\YellowCube\Model\Library\ClientFactory;

class ProductTest extends \Magento\TestFramework\TestCase\AbstractController
{

    /**
     * The yellowcube service mock.
     *
     * @var \YellowCube\Service|\PHPUnit\Framework\MockObject\MockObject
     */
    protected $yellowCubeServiceMock;

    protected function setUp()
    {
        parent::setUp();


        $this->yellowCubeServiceMock = $this->getMockBuilder(Service::class)->disableOriginalConstructor()->getMock();

        $clientFactoryMock = $this->getMockBuilder(ClientFactory::class)->disableOriginalConstructor()->getMock();
        $clientFactoryMock->expects($this->any())
            ->method('getService')
            ->willReturn($this->yellowCubeServiceMock);
        $this->_objectManager->addSharedInstance($clientFactoryMock, ClientFactory::class);
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
     * @magentoAppArea adminhtml
     */
    public function testEnabling()
    {
        $productRepository = $this->_objectManager->get(ProductRepositoryInterface::class);

        $product = $productRepository->get('simple1');
        $product->setData('yc_sync_with_yellowcube', true);
        $product->setData('yc_ean_type', 'HE');
        $product->setData('yc_ean_code', '135');
        $productRepository->save($product);

        $queue = $this->_objectManager->get(Queue::class);
        $this->assertCount(1, $queue->getMessages('yellowcube.sync'));

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

        /** @var \Magento\Framework\MessageQueue\ConsumerFactory $consumerFactory */
        $consumerFactory = $this->_objectManager->create(\Magento\Framework\MessageQueue\ConsumerFactory::class);
        $consumer = $consumerFactory->get('yellowcube.sync');
        $consumer->process(1);

    }
}
