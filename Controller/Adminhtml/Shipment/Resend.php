<?php

namespace Swisspost\YellowCube\Controller\Adminhtml\Shipment;

use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory;
use Magento\Shipping\Model\Shipment\Request;
use Magento\Ui\Component\MassAction\Filter;
use Swisspost\YellowCube\Model\Shipping\Carrier\Carrier;
use Swisspost\YellowCube\Model\Synchronizer;

class Resend extends \Magento\Backend\App\Action implements \Magento\Framework\App\ActionInterface
{

    /**
     * @var \Swisspost\YellowCube\Model\Synchronizer
     */
    protected $synchronizer;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory
     */
    protected $collectionFactory;

    /**
     * @var \Magento\Ui\Component\MassAction\Filter
     */
    protected $filter;

    /**
     * @var ShipmentRepositoryInterface
     */
    protected $shipmentRepository;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        Synchronizer $synchronizer,
        Filter $filter,
        ShipmentRepositoryInterface $shipmentRepository,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->synchronizer = $synchronizer;
        $this->shipmentRepository = $shipmentRepository;
        $this->collectionFactory = $collectionFactory;
    }

    /**
     * Execute action
     *
     * @return \Magento\Backend\Model\View\Result\Redirect
     * @throws \Magento\Framework\Exception\LocalizedException|\Exception
     */
    public function execute()
    {
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());

            /** @var Shipment $shipment */
            foreach ($collection as $shipment) {
                if (strpos($shipment->getOrder()->getShippingMethod(), 'yellowcube_') === 0 && (!$shipment->getShipmentStatus() || $shipment->getShipmentStatus() == \Swisspost\YellowCube\Model\Shipping\Carrier\Carrier::STATUS_ERROR)) {
                    $request = new Request();
                    $request->setOrderShipment($shipment);
                    $this->synchronizer->ship($request);

                    $shipment->setShipmentStatus(0);
                    $shipment->


                    $this->messageManager->addSuccessMessage(__('Shipment %1 has been resubmitted.', $shipment->getIncrementId()));
                } else {
                    $this->messageManager->addErrorMessage(__('Shipment %1 can not be resubmitted.', $shipment->getIncrementId()));
                }
            }
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        /** @var \Magento\Backend\Model\View\Result\Redirect $resultRedirect */
        $resultRedirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $resultRedirect->setPath('sales/shipment/index');
    }
}
