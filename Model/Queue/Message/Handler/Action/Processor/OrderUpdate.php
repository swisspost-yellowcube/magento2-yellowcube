<?php

/**
 * Liip AG
 *
 * @author      Sylvain Rayé <sylvain.raye at diglin.com>
 * @category    yellowcube
 * @package     Swisspost_yellowcube
 * @copyright   Copyright (c) 2015 Liip AG
 */

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor\Order;

use Swisspost\YellowCube\Helper\Data;

/**
 * Class Swisspost_YellowCube_Model_Queue_Message_Handler_Action_Processor_Order
 */
class OrderUpdate extends \Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract implements \Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface
{

    // 1440 = 24 hours * 5 days * (60/5 times per hour - cron job run each 5 minutes)
    const MAXTRIES = 1440;

    /**
     * @var \Magento\Sales\Model\Order\ShipmentFactory
     */
    protected $salesOrderShipmentFactory;

    /**
     * @var \Swisspost\YellowCube\Helper\Tools
     */
    protected $tools;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory,
        \Magento\Sales\Model\Order\ShipmentFactory $salesOrderShipmentFactory,
        \Swisspost\YellowCube\Helper\Tools $tools
    ) {
        parent::__construct($logger, $dataHelper, $clientFactory);
        $this->salesOrderShipmentFactory = $salesOrderShipmentFactory;
        $this->tools = $tools;
    }

    /**
     * Process WAB Status Update
     *
     * @param array $data
     * @return $this
     */
    public function process(array $data)
    {
        $shipment = $this->salesOrderShipmentFactory->create()->load($data['order_id'], 'order_id');
        $response = $this->getYellowCubeService()->getYCCustomerOrderStatus($data['yc_reference']);

        $this->logger->info(print_r($response, true));

        try {
            if (!is_object($response) || !$response->isSuccess()) {
                $message = __(
                    'Shipment #%s Status for Order #%s with YellowCube Transaction ID could not received from YellowCube: "%s".',
                    $shipment->getIncrementId(),
                    $data['order_id'],
                    $data['yc_reference'],
                    $response->getStatusText()
                );

                $shipment
                    ->addComment($message, false, false)
                    ->save();

                $this->logger->error($message . "\n" . print_r($response, true));
                $this->tools->sendAdminNotification($message);

                $this->resendMessageToQueue($data);
            } else {
                if ($response->isSuccess() && !$response->isPending() && !$response->isError()) {
                    $shipment
                        ->addComment(__('Success ' . $response->getStatusText()), false, false)
                        ->save();
                } else {
                    if ($response->isError()) {
                        $shipment
                            ->addComment(__('YellowCube Error: ' . $response->getStatusText()), false, false)
                            ->save();
                    } else {
                        if ($response->isPending()) {
                            $this->resendMessageToQueue($data);
                        }
                    }
                }

                if ($this->dataHelper->getDebug()) {
                    $this->logger->debug(print_r($response, true));
                }
            }
        } catch (\Exception $e) {
            $shipment
                ->addComment('Error: ' . $e->getMessage(), false, false)
                ->save();
            // Let's keep going further processes
            $this->resendMessageToQueue($data);

            $this->logger->critical($e);
        }

        return $this;
    }

    protected function resendMessageToQueue($data)
    {
        if (empty($data['try'])) {
            $data['try'] = 1;
        }
        if (isset($data['try']) && $data['try'] < self::MAXTRIES) {
            // Add again in the queue to have an up to date status
            $this->getQueue()->send(\Zend_Json::encode([
                'action' => Swisspost_YellowCube_Model_Synchronizer::SYNC_ORDER_UPDATE,
                'order_id' => $data['order_id'],
                'items' => $data['items'],
                'shipment_increment_id' => $data['shipment_increment_id'],
                'yc_reference' => $data['yc_reference'],
                'try' => $data['try'] + 1
            ]));
        }
    }
}
