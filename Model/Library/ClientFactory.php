<?php

namespace Swisspost\YellowCube\Model\Library;

use Magento\Framework\Exception\LocalizedException;
use Psr\Log\LogLevel;
use Swisspost\YellowCube\Helper\Data;
use YellowCube\Util\Logger\MinLevelFilterLogger;

/**
 *
 */
class ClientFactory
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Swisspost\YellowCube\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var \Magento\Framework\UrlInterface
     */
    protected $urlBuilder;

    /**
     * HandleProductSaveBefore constructor.
     * @param Data $dataHelper
     */
    public function __construct(Data $dataHelper, \Magento\Framework\UrlInterface $urlBuilder, \Psr\Log\LoggerInterface $logger)
    {
        $this->dataHelper = $dataHelper;
        $this->urlBuilder = $urlBuilder;
        $this->logger = $logger;
    }

    /**
     * @return \YellowCube\Service
     */
    public function getService()
    {
        $logger = new MinLevelFilterLogger(LogLevel::DEBUG, $this->logger);
        return new \YellowCube\Service($this->getServiceConfig(), null, $logger);
    }

    /**
     * @return \YellowCube\Config
     */
    public function getServiceConfig()
    {
        $certificatePath = $this->dataHelper->getCertificatePath();
        $certificatePassword = $this->dataHelper->getCertificatePassword();

        if (!$this->dataHelper->isConfigured()) {
            throw new LocalizedException(
                __('YellowCube Extension is not properly configured. Please <a href="%s">configure</a> it before to continue.',
                    $this->urlBuilder->getUrl('system_config/edit/section/carriers')));
        }

        $config = new \YellowCube\Config(
            $this->dataHelper->getSenderId(),
            $this->dataHelper->getEndpoint(),
            null,
            $this->dataHelper->getOperationMode()
        );

        // Certificate handling
        if (in_array($this->dataHelper->getOperationMode(), array('P', 'T', 'D'))) {
            if (!empty($certificatePath)) {
                $config->setCertificateFilePath($certificatePath, $certificatePassword);
            }
        }

        return $config;
    }
}
