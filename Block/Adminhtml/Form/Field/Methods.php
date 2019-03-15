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
     * @var Swisspost_YellowCube_Block_Adminhtml_Form_Field_Codes
     */
    protected $_methodRenderer;

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
     * Retrieve group column renderer
     *
     * @return Swisspost_YellowCube_Block_Adminhtml_Form_Field_Codes
     */
    protected function _getMethodRenderer()
    {
        if (!$this->_methodRenderer) {
            $this->_methodRenderer = $this->getLayout()->createBlock(
                Codes::class, '',
                array('is_render_to_js_template' => true)
            );
            $this->_methodRenderer->setClass('customer_group_select');
            $this->_methodRenderer->setExtraParams('style="width:120px"');
        }
        return $this->_methodRenderer;
    }

    /**
     * Prepare to render
     */
    protected function _prepareToRender()
    {
        $this->addColumn('allowed_methods', array(
            'label' => __('Methods'),
            'renderer' => $this->_getMethodRenderer(),
        ));
        $this->addColumn('price', array(
            'label' => __('Price'),
            'style' => 'width:100px',
        ));
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Shipping Method');
    }

    /**
     * Prepare existing row data object
     *
     * @param \Magento\Framework\DataObject
     */
    protected function _prepareArrayRow(\Magento\Framework\DataObject $row)
    {
        $row->setData(
            'option_extra_attr_' . $this->_getMethodRenderer()->calcOptionHash($row->getData('allowed_methods')),
            'selected="selected"'
        );
    }
}
