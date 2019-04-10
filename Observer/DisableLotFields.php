<?php

namespace Swisspost\YellowCube\Observer;

use Magento\Framework\Exception\LocalizedException;
use Swisspost\YellowCube\Helper\Data;

class DisableLotFields implements \Magento\Framework\Event\ObserverInterface
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
      $event = $observer->getEvent();
      $product = $event->getProduct();
      $product->lockAttribute('yc_stock');
      $product->lockAttribute('yc_lot_info');
      $product->lockAttribute('yc_most_recent_expiration_date');
    }
}
