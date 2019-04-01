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

use Magento\Backend\Block\Template\Context;
use Swisspost\YellowCube\Helper\Data;

/**
 * Class Swisspost_YellowCube_Block_Adminhtml_Form_Field_Methods
 */
class Methods extends \Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray
{
    /**
     * @var Codes
     */
    protected $_methodRenderer;

    /**
     * @var \Swisspost\YellowCube\Helper\Data
     */
    protected $dataHelper;

    /**
     * Methods constructor.
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
     * Retrieve group column renderer
     *
     * @return Codes
     *
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _getMethodRenderer()
    {
        if (!$this->_methodRenderer) {
            $this->_methodRenderer = $this->getLayout()->createBlock(
                Codes::class,
                '',
                [
                    'is_render_to_js_template' => true,
                    'data' => [
                        'id' =>  $this->_getCellInputElementId('<%- _id %>', 'allowed_methods'),
                    ],
                ]
            );
            $this->_methodRenderer->setClass('customer_group_select');
            $this->_methodRenderer->setExtraParams('style="width:200px"');
        }
        return $this->_methodRenderer;
    }

    /**
     * Prepare to render
     */
    protected function _prepareToRender()
    {
        $this->addColumn('allowed_methods', [
            'label' => __('Methods'),
            'renderer' => $this->_getMethodRenderer(),
        ]);
        $this->addColumn('price', [
            'label' => __('Price'),
            'style' => 'width:100px',
        ]);
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Shipping Method');
    }
}
