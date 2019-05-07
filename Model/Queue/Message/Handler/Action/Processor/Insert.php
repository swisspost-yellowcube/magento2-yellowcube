<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor;

use Magento\Framework\Exception\LocalizedException;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;
use YellowCube\ART\ChangeFlag;
use YellowCube\ART\UnitsOfMeasure\ISO;

class Insert extends ProcessorAbstract implements ProcessorInterface
{
    protected $_changeFlag = ChangeFlag::INSERT;

    /**
     * @param array $data
     * @return $this
     * @throws \Exception
     */
    public function process(array $data)
    {
        $uom = $data['product_uom'] === ISO::CMT;

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

        $response = $this->getYellowCubeService()->insertArticleMasterData($article);

        if (!is_object($response) || !$response->isSuccess()) {
            $message = __('%s has an error with the insertArticleMasterData() Service', $data['product_sku']);
            $this->logger->error($message . print_r($response, true));
            throw new LocalizedException($message);
        } else {
            if ($this->dataHelper->getDebug()) {
                $this->logger->debug(print_r($response, true));
            }
        }

        return $this;
    }
}
