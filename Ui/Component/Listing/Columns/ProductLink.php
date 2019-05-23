<?php

namespace Swisspost\YellowCube\Ui\Component\Listing\Columns;

use Magento\Backend\Model\UrlInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;

class ProductLink extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * Column name
     */
    const NAME = 'product_link';

    /**
     * @var ProductRepositoryInterface
     */
    private $productRepository;

    /**
     * @var UrlInterface
     */
    private $backendUrl;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param ProductRepositoryInterface $productRepository
     * @param UrlInterface $backendUrl
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        ProductRepositoryInterface $productRepository,
        UrlInterface $backendUrl,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->productRepository = $productRepository;
        $this->backendUrl = $backendUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as &$item) {
                $item['product_link'] = __('N\A');
                try {
                    $product = $this->productRepository->get($item['sku']);
                    $link = $this->backendUrl->getUrl('catalog/product/edit', ['id' => $product->getId()]);
                    $item[self::NAME] = sprintf('<a href="%s" target="_blank">%s</a>', $link, $product->getName());
                } catch (NoSuchEntityException $e) {
                    // do nothing
                }
            }
        }

        return $dataSource;
    }
}
