<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor;

use Magento\Sales\Api\Data\ShipmentItemInterface;
use Magento\Sales\Api\ShipmentTrackRepositoryInterface;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;
use Swisspost\YellowCube\Model\Shipping\Carrier\Carrier;
use Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository;

class War extends ProcessorAbstract implements ProcessorInterface
{

    /**
     * @var \Magento\Sales\Model\Order\Shipment\TrackFactory
     */
    protected $shipmentTrackFactory;

    /**
     * @var \Magento\Sales\Api\ShipmentRepositoryInterface
     */
    protected $shipmentRepository;

    /**
     * @var \Magento\Framework\Api\SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var \Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository
     */
    protected $yellowCubeShipmentItemRepository;

    /**
     * @var ShipmentTrackRepositoryInterface
     */
    protected $shipmentTrackRepository;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $shipmentTrackFactory,
        \Swisspost\YellowCube\Helper\Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory,
        \Magento\Sales\Api\ShipmentRepositoryInterface $shipmentRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        YellowCubeShipmentItemRepository $yellowCubeShipmentItemRepository,
        ShipmentTrackRepositoryInterface $shipmentTrackRepository
    ) {
        parent::__construct($logger, $dataHelper, $clientFactory);
        $this->shipmentTrackFactory = $shipmentTrackFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->yellowCubeShipmentItemRepository = $yellowCubeShipmentItemRepository;
        $this->shipmentTrackRepository = $shipmentTrackRepository;
    }
    /**
     * @param array $data
     */
    public function process(array $data)
    {
        try {
            // Only execute this query if there are any confirmed but not yet shipped shipments.
            if (!$this->yellowCubeShipmentItemRepository->getShipmentsByStatus(YellowCubeShipmentItemRepository::STATUS_CONFIRMED)) {
                return;
            }

            $goodsIssueList = $this->getYellowCubeService()->getYCCustomerOrderReply();

            foreach ($goodsIssueList as $goodsIssue) {
                $header = $goodsIssue->getCustomerOrderHeader();

                $searchCriteria = $this->searchCriteriaBuilder->addFilter('increment_id', $header->getCustomerOrderNo())->create();
                $shipmentList = $this->shipmentRepository->getList($searchCriteria)->getItems();
                $shipmentNo = $header->getPostalShipmentNo();

                // Multi packaging / shipping is not supported atm.
                if (!empty($shipmentNo) && $shipment = reset($shipmentList)) {
                    /**
                     * Define yc_shipped to 1 to be used later in BAR process that the shipping has been done
                     */
                    $customerOrderDetails = $goodsIssue->getCustomerOrderList();
                    $shipmentItems = $shipment->getItemsCollection();
                    $hash = [];

                    $lotId = null;
                    $quantityUOM = null;
                    try {
                        foreach ($customerOrderDetails as $customerOrderDetail) {
                            $this->logger->debug('Debug $customerOrderDetail ' . print_r($customerOrderDetail, true));

                            reset($shipmentItems);
                            /** @var ShipmentItemInterface $item */
                            foreach ($shipmentItems as $item) {
                                // $this->logger->debug('Debug $item ' . print_r($item, true));

                                if ($customerOrderDetail->getArticleNo() == $item->getSku() && !isset($hash[$item->getEntityId()])) {
                                    $this->yellowCubeShipmentItemRepository->updateByShipmentItemId($item->getEntityId(), YellowCubeShipmentItemRepository::STATUS_SHIPPED);
                                    $hash[$item->getEntityId()] = true;
                                }
                            }
                            $lotId = $customerOrderDetail->getLot();
                            $quantityUOM = $customerOrderDetail->getQuantityUOM();
                        }
                    } catch (\Exception $e) {
                        $this->logger->critical($e);
                    }

                    $this->logger->debug(__('Items for shipment %s considered as shipped', $shipment->getIncrementId()));

                    // Add a message to the order history incl. link to shipping infos
                    $message = __('Your order has been shipped, tracking #%1.', $shipmentNo);
                    if ($lotId) {
                        $message .= "\r\n" . __('Lot ID: %1', $lotId);
                    }
                    if ($quantityUOM) {
                        $message .= "\r\n" . __('Quantity UOM: %1', $quantityUOM);
                    }

                    $track = $this->shipmentTrackFactory->create();
                    $track
                        ->setCarrierCode(Carrier::CODE)
                        ->setTitle(__('SwissPost Tracking Code'))
                        ->setNumber($shipmentNo);

                    $shipment
                        ->addTrack($track)
                        ->addComment($message, true, true)
                        ->setShipmentStatus(Carrier::STATUS_SHIPPED)
                        ->save();

                    $this->logger->debug(__('Shipment %s comment added.', $shipment->getIncrementId()));
                }
            }

            if ($this->dataHelper->getDebug()) {
                $this->logger->debug(print_r($goodsIssueList, true));
            }
        } catch (\Exception $e) {
            // Let's keep going further processes
            $this->logger->critical($e);
        }

        return;
    }
}
