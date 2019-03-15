<?php

namespace Swisspost\YellowCube\Observer;

use Magento\Framework\Exception\LocalizedException;
use Swisspost\YellowCube\Helper\Data;

class HandleAttributeProductSaveBefore implements \Magento\Framework\Event\ObserverInterface
{

    /**
     * @var Data
     */
    protected $dataHelper;

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
        $productIds = $observer->getEvent()->getProductIds();
        $attributesData = $observer->getEvent()->getAttributesData();
        $storeId = $observer->getEvent()->getStoreId();

        $actionResource = $this->catalogResourceModelProductActionFactory->create();
        $yc = $actionResource->getAttribute('yc_sync_with_yellowcube');
        $ycl = $actionResource->getAttribute('yc_dimension_length');
        $ycw = $actionResource->getAttribute('yc_dimension_width');
        $ych = $actionResource->getAttribute('yc_dimension_height');
        $ycuom = $actionResource->getAttribute('yc_dimension_uom');
        $weight = $actionResource->getAttribute('weight');

        if (!$yc->getId()) {
            return;
        }

        foreach ($productIds as $key => $productId) {
            $productYCSync = $this->getAttributeData($productId, $storeId, $yc->getId());
            $productYCUom = $this->getAttributeData($productId, $storeId, $ycuom->getId(), 'varchar');
            $productYCLength = $this->getAttributeData($productId, $storeId, $ycl->getId(), 'decimal');
            $productYCWidth = $this->getAttributeData($productId, $storeId, $ycw->getId(), 'decimal');
            $productYCHeight = $this->getAttributeData($productId, $storeId, $ych->getId(), 'decimal');
            $productWeight = $this->getAttributeData($productId, $storeId, $weight->getId(), 'decimal');

            /**
             * If length/width/height in product is null => do nothing and doesn't allow to change the value
             */
            if (empty ($productYCLength) && empty($productYCWidth) && empty($productYCHeight)
                && empty($attributesData['yc_dimension_length']) && empty($attributesData['yc_dimension_width']) && empty($attributesData['yc_dimension_height'])
                && !empty($attributesData['yc_sync_with_yellowcube'])
            ) {

                if ((int) $productYCSync['value'] !== (int) $attributesData['yc_sync_with_yellowcube']) {
                    // Prepare to revert the changes - Note: cannot modify $productIds per reference as it is Mage_Catalog_Model_Product_Action::updateAttributes
                    $this->_attributeProductIds[$productId]['yc_sync_with_yellowcube'] = $productYCSync;
                }
                continue;
            }

            if (count($productYCSync) > 0) {
                if (isset($attributesData['yc_sync_with_yellowcube']) && (int) $productYCSync['value'] !== (int) $attributesData['yc_sync_with_yellowcube']) {
                    switch ((int) $attributesData['yc_sync_with_yellowcube']) {
                        case 0:
                            $this->getSynchronizer()->deactivate($this->catalogProductFactory->create()->load($productId));
                            break;
                        case 1:
                            $this->getSynchronizer()->insert($this->catalogProductFactory->create()->load($productId));
                            break;
                    }
                } else if ((int) $productYCSync['value']) {
                    // We handle size and weight changes if YC is enabled
                    if ((isset($attributesData['yc_dimension_length']) && $productYCLength['value'] != $attributesData['yc_dimension_length'])
                        || (isset($attributesData['yc_dimension_width']) && $productYCWidth['value'] != $attributesData['yc_dimension_width'])
                        || (isset($attributesData['yc_dimension_height']) && $productYCHeight['value'] != $attributesData['yc_dimension_height'])
                        || (isset($attributesData['yc_dimension_uom']) && $productYCUom['value'] != $attributesData['yc_dimension_uom'])
                        || (isset($attributesData['weight']) && $productWeight['value'] != $attributesData['weight'])
                    ) {
                        $this->getSynchronizer()->update($this->catalogProductFactory->create()->load($productId));
                    }
                }
            }
        }
    }
}
