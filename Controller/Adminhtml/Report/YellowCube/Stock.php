<?php

namespace Swisspost\YellowCube\Controller\Adminhtml\Report\YellowCube;

use Magento\Reports\Controller\Adminhtml\Report\AbstractReport;

abstract class Stock extends AbstractReport
{
    /**
     * YellowCube Stock report
     */
    public function execute()
    {
        $this->_initAction()->_setActiveMenu(
            'Swisspost_YellowCube::report_yellowcube_stock'
        )->_addBreadcrumb(
            __('YellowCube Stock'),
            __('YellowCube Stock')
        );
        $this->_view->getPage()->getConfig()->getTitle()->prepend(__('YellowCube Stock'));
        $this->_view->renderLayout();
    }
}
