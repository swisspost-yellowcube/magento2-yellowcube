<?php

namespace Swisspost\YellowCube\Helper;

use function is_string;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\ScopeInterface;

/**
 * Class Swisspost_YellowCube_Helper_Data
 */
class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    const CONFIG_SENDER_ID = 'carriers/yellowcube/sender_id';
    const CONFIG_CUSTOM_ENDPOINT = 'carriers/yellowcube/custom_url';
    const CONFIG_ENDPOINT = 'carriers/yellowcube/soap_url';
    const CONFIG_PARTNER_NUMBER = 'carriers/yellowcube/partner_number';
    const CONFIG_DEPOSITOR_NUMBER = 'carriers/yellowcube/depositor_number';
    const CONFIG_PLANT_ID = 'carriers/yellowcube/plant_id';
    const CONFIG_CERT_PATH = 'carriers/yellowcube/certificate_path';
    const CONFIG_CERT_PASSWORD = 'carriers/yellowcube/certificate_password';
    const CONFIG_TARA_FACTOR = 'carriers/yellowcube/tara_factor';
    const CONFIG_OPERATION_MODE = 'carriers/yellowcube/operation_mode';
    const CONFIG_DEBUG = 'carriers/yellowcube/debug';
    const CONFIG_ALLOWED_METHODS = 'carriers/yellowcube/allowed_methods';
    const CONFIG_SHIPPING_ADDITIONAL = 'carriers/yellowcube/additional_methods';
    const DEFAULT_ENDPOINTS = 'carriers/yellowcube/default_endpoints';

    const PARTNER_TYPE = 'WE';

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    protected $encrypter;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $jsonSerializer;

    /**
     * @var bool
     */
    protected $allowLockedAttributeChanges = FALSE;

    /**
     * Data constructor.
     *
     * @param Context $context
     * @param EncryptorInterface $encryptor
     */
    public function __construct(Context $context, EncryptorInterface $encryptor, Json $jsonSerializer)
    {
        parent::__construct($context);
        $this->encrypter = $encryptor;
        $this->jsonSerializer = $jsonSerializer;
    }

    /**
     * Get Sender Id
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getSenderId($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        $senderId = (string)$this->getConfigValue(self::CONFIG_SENDER_ID, $storeId);
        if ($storeId != \Magento\Store\Model\Store::ADMIN_CODE && $senderId) {
            return $senderId;
        } else {
            return (string)$this->getConfigValue(self::CONFIG_SENDER_ID);
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
        if ($this->getConfigValue(self::CONFIG_CUSTOM_ENDPOINT, $storeId)) {
            return (string)$this->getConfigValue(self::CONFIG_ENDPOINT, $storeId);
        } else {
            switch ($this->getOperationMode($storeId)) {
                case 'P':
                    return (string)$this->getConfigValue(self::DEFAULT_ENDPOINTS . '/production', $storeId);
                    break;

                case 'T':
                    return (string)$this->getConfigValue(self::DEFAULT_ENDPOINTS . '/test', $storeId);
                    break;

                case 'D':
                    return (string)$this->getConfigValue(self::DEFAULT_ENDPOINTS . '/development', $storeId);
                    break;
            }
        }
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
        return (string)$this->getConfigValue(self::CONFIG_PARTNER_NUMBER, $storeId);
    }

    /**
     * Get Depositor Number
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getDepositorNumber($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (string)$this->getConfigValue(self::CONFIG_DEPOSITOR_NUMBER, $storeId);
    }

    /**
     * Get Plant ID
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getPlantId($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (string)$this->getConfigValue(self::CONFIG_PLANT_ID, $storeId);
    }

    /**
     * Get certificate Path
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getCertificatePath($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (string)$this->getConfigValue(self::CONFIG_CERT_PATH, $storeId);
    }

    /**
     * Get certificate password
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getCertificatePassword($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return $this->encrypter->decrypt((string)$this->getConfigValue(self::CONFIG_CERT_PASSWORD, $storeId));
    }

    /**
     * Get Tara Factor (gross weight * tara factor = net weight)
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return float
     */
    public function getTaraFactor($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (float)$this->getConfigValue(self::CONFIG_TARA_FACTOR, $storeId);
    }

    /**
     * Get Operation mode P = Production, D = Development, T = Test
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return string
     */
    public function getOperationMode($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (string)$this->getConfigValue(self::CONFIG_OPERATION_MODE, $storeId);
    }

    /**
     * Get debug mode
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return bool
     */
    public function getDebug($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        return (bool)$this->getConfigValue(self::CONFIG_DEBUG, $storeId, true);
    }

    /**
     * Get debug mode
     *
     * @param null|string|bool|int|\Magento\Store\Model\Store $storeId
     * @return bool
     */
    public function getAllowedMethods($storeId = \Magento\Store\Model\Store::ADMIN_CODE)
    {
        $allowed_methods = $this->getConfigValue(self::CONFIG_ALLOWED_METHODS, $storeId);
        if (is_string($allowed_methods)) {
            $allowed_methods = $this->jsonSerializer->unserialize($allowed_methods);
        }
        return (array) $allowed_methods;
    }

    /**
     * @param $field
     * @param null $storeId
     * @return mixed
     */
    public function getConfigValue($field, $storeId = null)
    {
        return $this->scopeConfig->getValue($field, ScopeInterface::SCOPE_STORE, $storeId);
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
            || (in_array(
                $this->getOperationMode($storeId),
                    ['P']
            ) && empty($certificatePath) && empty($certificatePassword))
        ) {
            return false;
        }

        return true;
    }

    /**
     * Returns allowed methods.
     *
     * @return array
     *   The allowed shipping methods.
     */
    public function getMethods()
    {
        return (array)$this->scopeConfig->getValue('carriers/yellowcube/methods');
    }

    /**
     * Returns allowed methods.
     *
     * @return array
     *   The allowed shipping methods.
     */
    public function getEanTypes()
    {
        return (array)$this->scopeConfig->getValue('carriers/yellowcube/ean/type');
    }

    /**
     * Get real shipping code
     *
     * @param $shippingCode
     * @return string
     */
    public function getRealCode($shippingCode)
    {
        foreach ($this->getMethods() as $method) {
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
        foreach ($this->getMethods() as $method) {
            if ($method['code'] == $shippingCode) {
                if (isset($method['additional'])) {
                    return $method['additional'];
                }
                break;
            }
        }
        return 'NONE';
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

    /**
     * Check whether specified attribute has been changed for given entity.
     *
     * @param \Magento\Framework\Model\AbstractModel $entity
     *   The entity to check for changes.
     * @param array $keys
     *   The keys to check.
     *
     * @return bool
     *   TRUE if there was a change, false if not.
     */
    public function hasDataChangedFor(\Magento\Framework\Model\AbstractModel $entity, array $keys)
    {
        foreach ($keys as $key) {
            if ($entity->getOrigData($key) != $entity->getData($key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Transient flag to allow locked field changes, e.g. during inventory sync.
     *
     * @param bool|null $allow
     *   True or false to change the current value.
     *
     * @return bool
     *   Whether changes are allowed.
     */
    public function allowLockedAttributeChanges($allow = NULL) {
        if ($allow !== NULL) {
            $this->allowLockedAttributeChanges = $allow;
        }
        return $this->allowLockedAttributeChanges;
    }
}
