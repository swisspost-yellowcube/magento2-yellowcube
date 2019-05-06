<?php

namespace Swisspost\YellowCube\Block\Adminhtml\Report;

class YellowCubeStock extends \Magento\Backend\Block\Widget\Grid\Container
{
    /**
     * @return void
     */
    protected function _construct()
    {
        $this->_blockGroup = 'Swisspost_YellowCube';
        $this->_controller = 'adminhtml_report';
        $this->_headerText = __('YellowCube Stock');
        parent::_construct();
        $this->buttonList->remove('add');
    }
}
