<?php

namespace Swisspost\YellowCube\Helper;


/**
 * Class Swisspost_YellowCube_Helper_Data
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const CONFIG_SENDER_ID = 'carriers/yellowcube/sender_id';
    const CONFIG_ENDPOINT = 'carriers/yellowcube/soap_url';
    const CONFIG_PARTNER_NUMBER = 'carriers/yellowcube/partner_number';
    const CONFIG_DEPOSITOR_NUMBER = 'carriers/yellowcube/depositor_number';
    const CONFIG_PLANT_ID = 'carriers/yellowcube/plant_id';
    const CONFIG_CERT_PATH = 'carriers/yellowcube/certificate_path';
    const CONFIG_CERT_PASSWORD = 'carriers/yellowcube/certificate_password';
    const CONFIG_TARA_FACTOR = 'carriers/yellowcube/tara_factor';
    const CONFIG_OPERATION_MODE = 'carriers/yellowcube/operation_mode';
    const CONFIG_DEBUG = 'carriers/yellowcube/debug';
    const CONFIG_SHIPPING_ADDITIONAL = 'carriers/yellowcube/additional_methods';

    const YC_LOG_FILE = 'yellowcube.log';

    const PARTNER_TYPE = 'WE';
    public function __construct(
        \Magento\Framework\App\Helper\Context $context
    ) {
        parent::__construct(
            $context
        );
    }


    /**
     * Get Sender Id
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getSenderId($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        $senderId = (string)$this->getDefaultConfig(self::CONFIG_SENDER_ID, $storeId);
        if ($storeId != \Magento\Store\Model\Store::ADMIN_CODE && $senderId) {
            return $senderId;
        } else {
            return (string)$this->getDefaultConfig(self::CONFIG_SENDER_ID);
        }
    }

    /**
     * Get Soap Url Endpoint
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getEndpoint($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (string)$this->getDefaultConfig(self::CONFIG_ENDPOINT, $storeId);
    }

    /**
     * Get Partner Number
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getPartnerNumber($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (string)$this->getDefaultConfig(self::CONFIG_PARTNER_NUMBER, $storeId);
    }

    /**
     * Get Depositor Number
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getDepositorNumber($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (string)$this->getDefaultConfig(self::CONFIG_DEPOSITOR_NUMBER, $storeId);
    }

    /**
     * Get Plant ID
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getPlantId($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (string)$this->getDefaultConfig(self::CONFIG_PLANT_ID, $storeId);
    }

    /**
     * Get certificate Path
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getCertificatePath($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (string)$this->getDefaultConfig(self::CONFIG_CERT_PATH, $storeId);
    }

    /**
     * Get certificate password
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getCertificatePassword($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return Mage::helper('core')->decrypt((string)$this->getDefaultConfig(self::CONFIG_CERT_PASSWORD, $storeId));
    }

    /**
     * Get Tara Factor (gross weight * tara factor = net weight)
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return float
     */
    public function getTaraFactor($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (float)$this->getDefaultConfig(self::CONFIG_TARA_FACTOR, $storeId);
    }

    /**
     * Get Operation mode P = Production, D = Development, T = Test
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getOperationMode($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (string)$this->getDefaultConfig(self::CONFIG_OPERATION_MODE, $storeId);
    }

    /**
     * Get debug mode
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return bool
     */
    public function getDebug($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (bool)$this->getDefaultConfig(self::CONFIG_DEBUG, $storeId, true);
    }

    /**
     * @param $path
     * @param null $storeId
     * @return mixed
     */
    public function getDefaultConfig($path, $storeId = null, $flag = false)
    {
        $method = ($flag) ? 'getStoreConfigFlag' : 'getStoreConfig';
        $value = Mage::$method($path, $storeId);
        if ($storeId != \Magento\Store\Model\Store::ADMIN_CODE && !is_null($storeId) && !is_null($value)) {
            return $value;
        } else {
            return Mage::$method($path);
        }
    }

    /**
     * @param string $storeId
     * @return bool
     */
    public function isConfigured($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        $senderId = $this->getSenderId($storeId);
        $endpoint = $this->getEndpoint($storeId);
        $operationMode = $this->getOperationMode($storeId);
        $certificatePath = $this->getCertificatePath($storeId);
        $certificatePassword = $this->getCertificatePassword($storeId);

        if (empty($senderId) || empty($endpoint) || empty($operationMode)
            || (in_array($this->getOperationMode($storeId), array('P')) && empty($certificatePath) && empty($certificatePassword))
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get real shipping code
     *
     * @param $shippingCode
     * @return string
     */
    public function getRealCode($shippingCode)
    {
        foreach (Mage::getConfig()->getNode('global/carriers/yellowcube/methods')->asArray() as $method) {
            if ($method['code'] == $shippingCode) {
                if (isset($method['real_code'])) {
                    return $method['real_code'];
                }
                break;
            }
        }
        return $shippingCode;
    }

    /**
     * Get Additional Shipping Service
     *
     * @param $shippingCode
     * @return string
     */
    public function getAdditionalShipping($shippingCode)
    {
        foreach (Mage::getConfig()->getNode('global/carriers/yellowcube/methods')->asArray() as $method) {
            if ($method['code'] == $shippingCode) {
                if (isset($method['additional'])) {
                    return $method['additional'];
                }
                break;
            }
        }
        return '';
    }

    /**
     * @param string $func
     * @return bool
     */
    public function isFunctionAvailable($func)
    {
        if (ini_get('safe_mode')) {
            return false;
        }
        $disabled = ini_get('disable_functions');
        if ($disabled) {
            $disabled = explode(',', $disabled);
            $disabled = array_map('trim', $disabled);
            return !in_array($func, $disabled);
        }
        return true;
    }

    /**
     * @param string $fullName
     * @param string $zip
     * @return string
     */
    public function getPartnerReference($fullName, $zip)
    {
        $result = '';
        foreach (explode(' ', $fullName) as $name) {
            $result .= mb_strtoupper(mb_substr($name, 0, 1));
        }

        return $result . '-' . $zip;
    }
}
