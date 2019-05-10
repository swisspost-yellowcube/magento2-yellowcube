<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor;

use Magento\Framework\Exception\LocalizedException;
use Swisspost\YellowCube\Helper\Data;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;
use YellowCube\ART\ChangeFlag;
use YellowCube\ART\UnitsOfMeasure\ISO;

class Insert extends ProcessorAbstract implements ProcessorInterface
{
    protected $_changeFlag = ChangeFlag::INSERT;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $productRepository;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory,
        \Magento\Catalog\Model\ProductRepository $productRepository
    ) {
        parent::__construct($logger, $dataHelper, $clientFactory);
        $this->productRepository = $productRepository;

    }

    /**
     * @param array $data
     * @return $this
     * @throws \Exception
     */
    public function process(array $data)
    {
        $uom = ISO::CMT;

        $uomq = ISO::CMQ;

        $article = new \YellowCube\ART\Article();
        $article
            ->setChangeFlag($this->_changeFlag)
            ->setPlantID($data['plant_id'])
            ->setDepositorNo($data['deposit_number'])
            ->setBaseUOM(ISO::PCE)
            ->setAlternateUnitISO(ISO::PCE)
            ->setArticleNo($this->formatSku($data['product_sku']))
            ->setNetWeight(
                $this->formatUom($data['product_weight'] * $data['tara_factor']),
                ISO::KGM
            )
            ->setGrossWeight($this->formatUom($data['product_weight']), ISO::KGM)
            ->setLength($this->formatUom($data['product_length']), $uom)
            ->setWidth($this->formatUom($data['product_width']), $uom)
            ->setHeight($this->formatUom($data['product_height']), $uom)
            ->setVolume($this->formatUom($data['product_volume']), $uomq)
            ->setEAN($data['product_ean'], $data['product_ean_type'])
            ->setBatchMngtReq((int) $data['product_lot_management'])
            // @todo provide the language of the current description (possible values de|fr|it|en)
            ->addArticleDescription($this->formatDescription($data['product_name']), 'de');

        try {
            $response = $this->getYellowCubeService()->insertArticleMasterData($article);
        } catch (\Exception $e) {
            if ($this->_changeFlag !== ChangeFlag::DEACTIVATE) {
                $product = $this->productRepository->get($data['product_sku']);
                $product->setData('yc_response', $e->getMessage());
                $product->setData('yc_sync_with_yellowcube', false);
                // Set a transient flag to ignore this in the Observer.
                $product->setData('yc_ignore', true);
                $this->productRepository->save($product);
            }
            $this->logger->error('YellowCube ART: ' . $e->getMessage());
            throw $e;
        }

        if (!$response->isSuccess()) {
            $message = __('%s has an error with the insertArticleMasterData() Service', $data['product_sku']);
            $this->logger->error($message . print_r($response, true));
            if ($this->_changeFlag !== ChangeFlag::DEACTIVATE) {
                $product = $this->productRepository->get($data['product_sku']);
                $product->setData('yc_response', $response->getStatusText());
                $product->setData('yc_sync_with_yellowcube', false);
                // Set a transient flag to ignore this in the Observer.
                $product->setData('yc_ignore', true);
                $this->productRepository->save($product);
            }
            throw new LocalizedException($message);
        } else {
            if ($this->dataHelper->getDebug()) {
                $this->logger->debug(print_r($response, true));
            }

            if ($this->_changeFlag !== ChangeFlag::DEACTIVATE) {
                $product = $this->productRepository->get($data['product_sku']);
                $product->setData('yc_reference', $response->getReference());
                $this->productRepository->save($product);
            }

        }

        return $this;
    }
}
