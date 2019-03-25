<?php

namespace Swisspost\YellowCube\Observer;

use Magento\Framework\Exception\LocalizedException;
use Swisspost\YellowCube\Helper\Data;

class HandleProductSaveAfter implements \Magento\Framework\Event\ObserverInterface
{

    /**
     * @var \Swisspost\YellowCube\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var array
     */
    protected $_attributeProductIds = [];

    /**
     * HandleProductSaveBefore constructor.
     * @param Data $dataHelper
     */
    public function __construct(Data $dataHelper)
    {
        $this->dataHelper = $dataHelper;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return $this|void
     *
     * @throws LocalizedException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        if (count($this->_attributeProductIds) > 0) {
            $actionResource = $this->catalogResourceModelProductActionFactory->create();
            foreach ($this->_attributeProductIds as $productId => $attributes) {
                foreach ($attributes as $key => $attribute) {
                    if ($key == 'yc_sync_with_yellowcube') {
                        // Revert the changes done during the mass update of product attributes only if products doesn't have length/width/height
                        $actionResource->updateAttributes(array($productId), array($key => $attribute['value']), $attribute['store_id']);
                    }
                }
            }
        }
        return $this;
    }
}
