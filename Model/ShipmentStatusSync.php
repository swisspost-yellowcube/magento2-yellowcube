<?php

/**
 * Liip AG
 *
 * @author      Sylvain Rayé <sylvain.raye at diglin.com>
 * @category    yellowcube
 * @package     Swisspost_yellowcube
 * @copyright   Copyright (c) 2015 Liip AG
 */

namespace Swisspost\YellowCube\Model;

use Magento\Sales\Api\Data\ShipmentInterface;
use Swisspost\YellowCube\Helper\Data;

/**
 * Class Swisspost_YellowCube_Model_Queue_Message_Handler_Action_Processor_Order
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
                $this->process($shipment, $reference);
            } catch (\Exception $e) {
                if ($shipment) {
                    $shipment->addComment('YellowCube Error: ' . $e->getMessage(), false, false);
                    $this->shipmentRepository->save($shipment);
                }
                $this->yellowCubeShipmentItemRepository->updateByShipmentId($shipmentId, YellowCubeShipmentItemRepository::STATUS_ERROR, $e->getMessage());
                $this->logger->error($e->getMessage());
            }
        }
    }

    protected function process(ShipmentInterface $shipment, $reference)
    {
        $response = $this->clientFactory->getService()->getYCCustomerOrderStatus($reference);

        $this->logger->info(print_r($response, true));

        if (!is_object($response) || !$response->isSuccess()) {
            $message = __(
                    'Shipment #%s Status for Order #%s with YellowCube Transaction ID could not received from YellowCube: "%s".',
                    $shipment->getIncrementId(),
                    $shipment->getOrderId(),
                    $reference,
                    $response->getStatusText()
                );

            $shipment
                    ->addComment($message, false, false)
                    ->save();

            $this->logger->error($message . "\n" . print_r($response, true));
        } else {
            if ($response->isSuccess() && !$response->isPending() && !$response->isError()) {
                $this->yellowCubeShipmentItemRepository->updateByShipmentId($shipment->getId(), YellowCubeShipmentItemRepository::STATUS_CONFIRMED);
                $shipment
                        ->addComment(__('YellowCube Success ' . $response->getStatusText()), false, false)
                        ->save();
            } else {
                if ($response->isError()) {
                    $shipment
                            ->addComment(__('YellowCube Error: ' . $response->getStatusText()), false, false)
                            ->save();
                }
            }

            if ($this->dataHelper->getDebug()) {
                $this->logger->debug(print_r($response, true));
            }
        }

        return $this;
    }

}
