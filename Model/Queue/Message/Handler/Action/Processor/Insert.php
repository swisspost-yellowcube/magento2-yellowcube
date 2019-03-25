<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor;

use Swisspost\YellowCube\Model\Dimension\Uom\Attribute\Source;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;

class Insert
    extends ProcessorAbstract
    implements ProcessorInterface
{
    protected $_changeFlag = \YellowCube\ART\ChangeFlag::INSERT;

    /**
     * @param array $data
     * @return $this
     * @throws \Exception
     */
    public function process(array $data)
    {
        $uom = $data['product_uom'] === Source::VALUE_MTR
            ? \YellowCube\ART\UnitsOfMeasure\ISO::MTR
            : \YellowCube\ART\UnitsOfMeasure\ISO::CMT;

        $uomq = ($uom == \YellowCube\ART\UnitsOfMeasure\ISO::MTR) ? \YellowCube\ART\UnitsOfMeasure\ISO::MTQ : \YellowCube\ART\UnitsOfMeasure\ISO::CMQ;

        $article = new \YellowCube\ART\Article;
        $article
            ->setChangeFlag($this->_changeFlag)
            ->setPlantID($data['plant_id'])
            ->setDepositorNo($data['deposit_number'])
            ->setBaseUOM(\YellowCube\ART\UnitsOfMeasure\ISO::PCE)
            ->setAlternateUnitISO(\YellowCube\ART\UnitsOfMeasure\ISO::PCE)
            ->setArticleNo($this->formatSku($data['product_sku']))
            ->setNetWeight($this->formatUom($data['product_weight'] * $data['tara_factor']),
                \YellowCube\ART\UnitsOfMeasure\ISO::KGM)
            ->setGrossWeight($this->formatUom($data['product_weight']), \YellowCube\ART\UnitsOfMeasure\ISO::KGM)
            ->setLength($this->formatUom($data['product_length']), $uom)
            ->setWidth($this->formatUom($data['product_width']), $uom)
            ->setHeight($this->formatUom($data['product_height']), $uom)
            ->setVolume($this->formatUom($data['product_volume']), $uomq)
            ->setEAN($data['product_ean'], $data['product_ean_type'])
            ->setBatchMngtReq($data['product_lot_management'])
            // @todo provide the language of the current description (possible values de|fr|it|en)
            ->addArticleDescription($this->formatDescription($data['product_name']), 'de');

        $response = $this->getYellowCubeService()->insertArticleMasterData($article);

        if (!is_object($response) || !$response->isSuccess()) {
            $message = __('%s has an error with the insertArticleMasterData() Service', $data['product_sku']);
            $this->logger->error($message . print_r($response, true));
            throw new \Magento\Framework\Exception\LocalizedException($message);
        } else {
            if ($this->dataHelper->getDebug()) {
                $this->logger->debug(print_r($response, true));
            }
        }

        return $this;
    }
}
