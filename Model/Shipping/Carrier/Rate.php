<?php

namespace Swisspost\YellowCube\Model\Shipping\Carrier\Source;

use Magento\Shipping\Model\Carrier\CarrierInterface;

class Rate extends \Magento\Shipping\Model\Carrier\AbstractCarrier
    implements CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'yellowcube';

    /**
     * @var bool
     */
    protected $_isFixed = true;

    /**
     * @var \Magento\Shipping\Model\Rate\ResultFactory
     */
    protected $shippingRateResultFactory;

    /**
     * @var \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory
     */
    protected $quoteQuoteAddressRateResultMethodFactory;

    /**
     * @var \Magento\Framework\DataObjectFactory
     */
    protected $dataObjectFactory;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $shippingRateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $quoteQuoteAddressRateResultMethodFactory,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        array $data = []
    ) {
        $this->dataObjectFactory = $dataObjectFactory;
        $this->shippingRateResultFactory = $shippingRateResultFactory;
        $this->quoteQuoteAddressRateResultMethodFactory = $quoteQuoteAddressRateResultMethodFactory;
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $data
        );
    }


    /**
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return bool|\Magento\Shipping\Model\Rate\Result
     */
    public function collectRates(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->shippingRateResultFactory->create();

        foreach ($this->getAllowedMethods() as $methodCode => $methodName) {
            /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
            $method = $this->quoteQuoteAddressRateResultMethodFactory->create();
            $method->setCarrier($this->_code);
            $method->setCarrierTitle($this->getConfigData('title'));
            $method->setMethod($methodCode);
            $method->setMethodTitle($methodName);
            $method->setPrice($this->getPriceMethod($methodCode));
            $method->setCost(0);

            $result->append($method);
        }

        return $result;
    }

    /**
     * Get the price depending the method
     *
     * @param $code
     * @return int
     */
    public function getPriceMethod($code)
    {
        $allowedMethods = unserialize($this->getConfigData('allowed_methods'));

        foreach ($allowedMethods as $method) {
            if ($method['allowed_methods'] == $code) {
                return $method['price'];
            }
        }
        return 0;
    }

    /**
     * Get allowed Methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        $methods = Mage::getSingleton('swisspost_yellowcube/shipping_carrier_source_method')->getMethods();
        $allowedMethods = unserialize($this->getConfigData('allowed_methods'));

        $allowed = array();
        foreach ($allowedMethods as $method) {
            $allowed[$method['allowed_methods']] = $method['allowed_methods'];
        }

        $arr = array();
        foreach ($methods as $key => $method) {
            /* @var $method Mage_Core_Model_Config_Element */
            if (array_key_exists($key, $allowed)) {
                $arr[$key] = $methods[$key];
            }
        };

        return $arr;
    }

    /**
     * @param \Magento\Shipping\Model\Shipment\Request $request
     * @return \Magento\Framework\DataObject
     */
    public function requestToShipment($request)
    {
        // No error is returned as it is an asynchron process with yellowcube
        Mage::getSingleton('swisspost_yellowcube/synchronizer')->ship($request);

        return $this->dataObjectFactory->create();
    }
}
