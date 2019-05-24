<?php

namespace Swisspost\YellowCube\Model\Shipping\Carrier;

use Magento\Shipping\Model\Carrier\CarrierInterface;
use Swisspost\YellowCube\Model\Synchronizer;

class Carrier extends \Magento\Shipping\Model\Carrier\AbstractCarrier implements CarrierInterface
{
    const CODE = 'yellowcube';

    const STATUS_SUBMITTED = 46650001;

    const STATUS_CONFIRMED = 46650002;

    const STATUS_SHIPPED = 46650003;

    const STATUS_ERROR = 46650099;

    /**
     * @var string
     */
    protected $_code = self::CODE;

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

    /**
     * @var \Swisspost\YellowCube\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var \Swisspost\YellowCube\Model\Synchronizer
     */
    protected $synchronizer;

    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Shipping\Model\Rate\ResultFactory $shippingRateResultFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $quoteQuoteAddressRateResultMethodFactory,
        \Magento\Framework\DataObjectFactory $dataObjectFactory,
        Synchronizer $synchronizer,
        \Swisspost\YellowCube\Helper\Data $dataHelper,
        array $data = []
    ) {
        parent::__construct($scopeConfig, $rateErrorFactory, $logger, $data);
        $this->dataObjectFactory = $dataObjectFactory;
        $this->shippingRateResultFactory = $shippingRateResultFactory;
        $this->quoteQuoteAddressRateResultMethodFactory = $quoteQuoteAddressRateResultMethodFactory;
        $this->synchronizer = $synchronizer;
        $this->dataHelper = $dataHelper;
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
        if (!$this->getConfigData('active')) {
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
        $allowedMethods = $this->dataHelper->getAllowedMethods();

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
        $allowedMethods = array_column($this->dataHelper->getAllowedMethods(), 'allowed_methods');

        $methods = [];
        foreach ($this->dataHelper->getMethods() as $method) {
            if (in_array($method['code'], $allowedMethods)) {
                $methods[$method['code']] = $method['label'];
            }
        }
        return $methods;
    }

    /**
     * @param \Magento\Shipping\Model\Shipment\Request $request
     * @return \Magento\Framework\DataObject
     */
    public function requestToShipment($request)
    {
        // No error is returned as it is an asynchron process with yellowcube
        $this->synchronizer->ship($request);

        return $this->dataObjectFactory->create();
    }

    /**
     * Returns known shipment status options.
     *
     * @return array
     */
    public function getShipmentStatusOptions()
    {
        return [
            static::STATUS_SUBMITTED => __('Submitted'),
            static::STATUS_CONFIRMED => __('Confirmed'),
            static::STATUS_SHIPPED => __('Shipped'),
            static::STATUS_ERROR => __('Error'),
        ];

    }
}
