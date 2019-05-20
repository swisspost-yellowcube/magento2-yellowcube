<?php

namespace Swisspost\YellowCube\Model;

use Swisspost\YellowCube\Helper\Data;

/**
 * Checks for confirmations about submitted article inserts and updates to YellowCube.
 */
class ArticleResponseSync
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @var \Swisspost\YellowCube\Model\Library\ClientFactory
     */
    protected $clientFactory;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $productRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
        $this->clientFactory = $clientFactory;
        $this->productRepository = $productRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    public function processArticles()
    {

        $searchCriteria = $this->searchCriteriaBuilder->addFilter('yc_reference', null, 'notnull')->create();
        $products = $this->productRepository->getList($searchCriteria)->getItems();
        if (!$products) {
            return;
        }

        $service = $this->clientFactory->getService();

        foreach ($products as $product) {
            $shipment = null;
            try {
                $response = $service->getInsertArticleMasterDataStatus($product->getData('yc_reference'));

                if ($response->isPending()) {
                    // Response is still pending, ignore it.
                    continue;
                }

                if ($response->isError()) {
                    $this->logger->error('YellowCube ART Error: ' . $response->getStatusText());
                    $product->setData('yc_response', $response->getStatusText());
                    $product->setData('yc_sync_with_yellowcube', false);
                }

                $product->setData('yc_reference', null);
                $this->productRepository->save($product);

            } catch (\Exception $e) {
                $this->logger->error('YellowCube ART Exception: ' . $e->getMessage());
                $product->setData('yc_response', $e->getMessage());
                $product->setData('yc_sync_with_yellowcube', false);

                $product->setData('yc_reference', null);
                $this->productRepository->save($product);

            }
        }
    }

}
