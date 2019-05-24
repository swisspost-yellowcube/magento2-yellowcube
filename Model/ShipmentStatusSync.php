<?php

namespace Swisspost\YellowCube\Model;

use Magento\Sales\Api\Data\ShipmentInterface;
use Swisspost\YellowCube\Helper\Data;
use Swisspost\YellowCube\Model\Shipping\Carrier\Carrier;

/**
 * Checks for confirmations about submitted shipments to YellowCube.
 */
class ShipmentStatusSync
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @var \Swisspost\YellowCube\Model\Library\ClientFactory
     */
    protected $clientFactory;

    /**
     * @var \Magento\Sales\Model\Order\ShipmentRepository
     */
    protected $shipmentRepository;

    /**
     * @var \Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository
     */
    protected $yellowCubeShipmentItemRepository;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory,
        \Magento\Sales\Model\Order\ShipmentRepository $shipmentRepository,
        YellowCubeShipmentItemRepository $yellowCubeShipmentItemRepository
    ) {
        $this->logger = $logger;
        $this->dataHelper = $dataHelper;
        $this->clientFactory = $clientFactory;
        $this->shipmentRepository = $shipmentRepository;
        $this->yellowCubeShipmentItemRepository = $yellowCubeShipmentItemRepository;
    }

    public function processPendingShipments()
    {
        foreach ($this->yellowCubeShipmentItemRepository->getUnconfirmedShipments() as $shipmentId => $reference) {
            $shipment = null;
            try {
                $shipment = $this->shipmentRepository->get($shipmentId);
                $response = $this->clientFactory->getService()->getYCCustomerOrderStatus($reference);
                if ($response->isSuccess() && !$response->isPending()) {
                    $this->yellowCubeShipmentItemRepository->updateByShipmentId($shipment->getEntityId(), YellowCubeShipmentItemRepository::STATUS_CONFIRMED);
                    $shipment->addComment(__('YellowCube Success ' . $response->getStatusText()), false, false);
                    $shipment->setShipmentStatus(Carrier::STATUS_CONFIRMED);
                    $shipment->save();
                }
            } catch (\Exception $e) {
                if ($shipment) {
                    $shipment->addComment('YellowCube Error: ' . $e->getMessage(), false, false);
                    $shipment->setShipmentStatus(Carrier::STATUS_ERROR);
                    $this->shipmentRepository->save($shipment);
                }
                $this->yellowCubeShipmentItemRepository->updateByShipmentId($shipmentId, YellowCubeShipmentItemRepository::STATUS_ERROR, $e->getMessage());
                $this->logger->error($e->getMessage());
            }
        }
    }
}
