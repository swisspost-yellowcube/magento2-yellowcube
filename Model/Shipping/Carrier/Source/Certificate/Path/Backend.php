<?php
/**
 * Liip AG
 *
 * @author      Sylvain Rayé <sylvain.raye at diglin.com>
 * @category    yellowcube
 * @package     Swisspost_yellowcube
 * @copyright   Copyright (c) 2015 Liip AG
 */

namespace Swisspost\YellowCube\Model\Shipping\Carrier\Source\Certificate\Path;

class Backend extends \Magento\Framework\App\Config\Value
{
    public function __construct(
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\App\Config\ScopeConfigInterface $config,
        \Magento\Framework\App\Cache\TypeListInterface $cacheTypeList,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $config,
            $cacheTypeList,
            $resource,
            $resourceCollection,
            $data
        );
    }

    /**
     * Checks if the certificate is available and readable.
     *
     * @return \Magento\Framework\Model\AbstractModel
     * @throws \Exception
     */
    protected function _beforeSave()
    {
        $filePath = $this->getValue();
        if (!empty($filePath) && (!file_exists($filePath) || !is_readable($filePath))) {
            throw new \Exception(
                __("Failed to load certificate from '%s'", $filePath)
            );
        }

        return parent::_beforeSave();
    }
}
