<?php

namespace Swisspost\YellowCube\Observer;

use Magento\Framework\Exception\LocalizedException;
use Swisspost\YellowCube\Helper\Data;

class HandleProductSaveBefore implements \Magento\Framework\Event\ObserverInterface
{

    /**
     * @var \Swisspost\YellowCube\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var array
     */
    protected $_attributeProductIds;
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
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getDataObject();

        // @todo Make it work with multiple websites. Note: value of yc_sync_with_yellowcube on product save doesn't
        // reflect the value of the default view if "Use default Value" is checked

        if (!$this->dataHelper->isConfigured(/* $storeId */) && (bool)$product->getData('yc_sync_with_yellowcube')) {
            throw new LocalizedException($this->dataHelper->__('Please, configure YellowCube before to save the product having YellowCube option enabled.'));
        } else {
            if (!$this->dataHelper->isConfigured(/* $storeId */)) {
                return $this;
            }
        }

        /**
         * Scenario
         *
         * - product is disabled or enabled => no change
         * - yc_sync_with_yellowcube is Yes/No
         *   - From No to Yes => insert into YC
         *   - From No to No => no change
         *   - From Yes to No => deactivate from YC
         *
         * - if duplicate, we do nothing as the attribute 'yc_sync_with_yellowcube' = 0
         */

        if ((bool)$product->getData('yc_sync_with_yellowcube') && $this->hasDataChangedFor($product,
                array('yc_sync_with_yellowcube'))) {
            $this->getSynchronizer()->insert($product);
            return $this;
        }

        if (!(bool)$product->getData('yc_sync_with_yellowcube') && $this->hasDataChangedFor($product,
                array('yc_sync_with_yellowcube'))) {
            $this->getSynchronizer()->deactivate($product);
            return $this;
        }

        if (!(bool)$product->getData('yc_sync_with_yellowcube')) {
            return $this;
        }

        if ($this->hasDataChangedFor($product, [
            'name',
            'weight',
            'yc_dimension_length',
            'yc_dimension_width',
            'yc_dimension_height',
            'yc_dimension_uom'
        ])) {
            $this->getSynchronizer()->update($product);
            return $this;
        }
    }
}
