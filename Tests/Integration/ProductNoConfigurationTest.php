<?php

namespace Swisspost\YellowCube\Tests\Integration;

use Magento\Catalog\Api\ProductRepositoryInterface;

class ProductNoConfigurationTest extends YellowCubeTestBase
{

    public static function loadFixture()
    {
        include __DIR__ . '/../_files/products.php';
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoAppIsolation enabled
     * @magentoDataFixture loadFixture
     * @magentoAppArea adminhtml
     *
     * @expectedException \Magento\Framework\Exception\LocalizedException
     * @expectedExceptionMessage Configure YellowCube before to save the product having YellowCube option enabled.
     */
    public function testEnablingWithoutConfiguration()
    {
        $productRepository = $this->_objectManager->get(ProductRepositoryInterface::class);

        $product = $productRepository->get('simple1');
        $product->setData('yc_sync_with_yellowcube', true);
        $productRepository->save($product);
    }
}
