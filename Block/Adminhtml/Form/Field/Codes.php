<?php
/**
 * Liip AG
 *
 * @author      Sylvain Rayé <sylvain.raye at diglin.com>
 * @category    yellowcube
 * @package     Swisspost_yellowcube
 * @copyright   Copyright (c) 2015 Liip AG
 */

namespace Swisspost\YellowCube\Block\Adminhtml\Form\Field;

use Magento\Framework\View\Element\Context;
use Swisspost\YellowCube\Helper\Data;

/**
 * Shipping codes HTML select element.
 */
class Codes extends \Magento\Framework\View\Element\Html\Select
{
    /**
     * Carrier Code
     *
     * @var array
     */
    private $_codes;

    /**
     * @var \Swisspost\YellowCube\Helper\Data
     */
    protected $dataHelper;

    /**
     * Codes constructor.
     *
     * @param Context $context
     * @param Data $dataHelper
     * @param array $data
     */
    public function __construct(Context $context, Data $dataHelper, array $data = [])
    {
        parent::__construct($context, $data);
        $this->dataHelper = $dataHelper;
    }

    /**
     * Retrieve allowed carrier codes
     *
     * @param int $code
     * @return array|string
     */
    protected function _getCarrierCodes($code = null)
    {
        if ($this->_codes === null) {
            $this->_codes = [];
            $codes = $this->dataHelper->getMethods();

            foreach ($codes as $key => $item) {
                $this->_codes[$item['code']] = $item['label'];
            }
        }
        if (!is_null($code)) {
            return isset($this->_codes[$code]) ? $this->_codes[$code] : null;
        }
        return $this->_codes;
    }

    public function setInputName($value)
    {
        return $this->setName($value);
    }

    /**
     * Render block HTML
     *
     * @return string
     */
    public function _toHtml()
    {
        if (!$this->getOptions()) {
            foreach ($this->_getCarrierCodes() as $code => $label) {
                $this->addOption($code, addslashes($label));
            }
        }
        return parent::_toHtml();
    }
}
