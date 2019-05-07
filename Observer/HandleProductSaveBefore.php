<?php

namespace Swisspost\YellowCube\Observer;

use Magento\Framework\Exception\LocalizedException;
use Swisspost\YellowCube\Helper\Data;
use Swisspost\YellowCube\Model\Synchronizer;

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
     * @var Synchronizer
     */
    protected $synchronizer;

    /**
     * @var \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable
     */
    protected $catalogProductTypeConfigurable;

    /**
     * HandleProductSaveBefore constructor.
     * @param Data $dataHelper
     */
    public function __construct(
        \Swisspost\YellowCube\Helper\Data $dataHelper,
        \Swisspost\YellowCube\Model\Synchronizer $synchronizer,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $catalogProductTypeConfigurable
    ) {
        $this->dataHelper = $dataHelper;
        $this->synchronizer = $synchronizer;
        $this->catalogProductTypeConfigurable = $catalogProductTypeConfigurable;
    }

    /**
     * @param \Magento\Framework\Event\Observer $observer
     *
     * @return void
     *
     * @throws LocalizedException
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        /** @var \Magento\Catalog\Model\Product $product */
        $product = $observer->getEvent()->getDataObject();

        // @todo Make it work with multiple websites. Note: value of yc_sync_with_yellowcube on product save doesn't
        // reflect the value of the default view if "Use default Value" is checked

        $is_configured = $this->dataHelper->isConfigured(/* $storeId */);
        $sync = $product->getData('yc_sync_with_yellowcube');

        if (!$is_configured && (bool)$sync) {
            throw new LocalizedException(__('Configure YellowCube before to save the product having YellowCube option enabled.'));
        } else {
            if (!$is_configured) {
                return;
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

        if ($this->dataHelper->hasDataChangedFor($product, ['yc_sync_with_yellowcube'])) {
            if ((bool)$product->getData('yc_sync_with_yellowcube')) {
                $this->synchronizer->insert($product);
                return;
            } else {
                $this->synchronizer->deactivate($product);
                return;
            }
        }

        if (!(bool)$product->getData('yc_sync_with_yellowcube')) {
            return;
        }

        $attributes = ['name', 'weight', 'ts_dimensions_length', 'ts_dimensions_width', 'ts_dimensions_height', 'ts_dimensions_uom', 'yc_ean_type', 'yc_ean_code'];

        if ($this->dataHelper->hasDataChangedFor($product, $attributes)) {
            $this->synchronizer->update($product);
            return;
        }
    }
}
