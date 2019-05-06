<?php
namespace Swisspost\YellowCube\Block\Adminhtml\Report;

use Swisspost\YellowCube\Model\StockCollection;

/**
 * Adminhtml YellowCube stock report grid block.
 */
class Grid extends \Magento\Backend\Block\Widget\Grid
{
    /**
     * @var \Magento\Reports\Model\ResourceModel\Product\Lowstock\CollectionFactory
     */
    protected $stockCollection;

    /**
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Backend\Helper\Data $backendHelper
     * @param \Magento\Reports\Model\ResourceModel\Product\Lowstock\CollectionFactory $lowstocksFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        StockCollection $stockCollection,
        array $data = []
    ) {
        $this->stockCollection = $stockCollection;
        parent::__construct($context, $backendHelper, $data);
    }

}
