<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action;

use Magento\Framework\Serialize\Serializer\Json;
use Swisspost\YellowCube\Helper\Data;
use Swisspost\YellowCube\Helper\FormatHelper;

abstract class ProcessorAbstract implements ProcessorInterface
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

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory
    ) {
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
        $this->clientFactory = $clientFactory;
    }

    /**
     * @return \\YellowCube\Service
     */
    public function getYellowCubeService()
    {
        return $this->clientFactory->getService();
    }

    /**
     * @param float $number
     * @return string
     */
    public function formatUom($number)
    {
        return number_format($number, 3, '.', '');
    }

    /**
     * @param $description
     * @return string
     */
    public function formatDescription($description)
    {
        return mb_strcut($description, 0, 40);
    }

    /**
     * @param string $sku
     * @return string
     */
    public function formatSku($sku)
    {
        return str_replace(' ', '_', $sku);
    }

    /**
     * @param $string
     * @return string
     */
    public function cutString($string, $length = 35)
    {
        return mb_strcut($string, 0, $length);
    }

    /**
     * @param $elem
     * @param $array
     * @return bool
     */
    public function inMultiArray($elem, $array)
    {
        foreach ($array as $key => $value) {
            if ($value == $elem) {
                return true;
            } elseif (is_array($value)) {
                if ($this->inMultiArray($elem, $value))
                    return true;
            }
        }

        return false;
    }

}
