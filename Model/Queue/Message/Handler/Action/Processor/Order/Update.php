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

use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;

/**
 * Class Swisspost_YellowCube_Model_Queue_Message_Handler_Action_Processor_Order
 */
class Update extends ProcessorAbstract implements ProcessorInterface {

    // 1440 = 24 hours * 5 days * (60/5 times per hour - cron job run each 5 minutes)
    const MAXTRIES = 1440;

    /**
     * @var \Magento\Sales\Model\Order\ShipmentFactory
     */
    protected $salesOrderShipmentFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \Magento\Sales\Model\Order\ShipmentFactory $salesOrderShipmentFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->salesOrderShipmentFactory = $salesOrderShipmentFactory;
        $this->logger = $logger;
    }
    /**
     * Process WAB Status Update
     *
     * @param array $data
     * @return $this
     */
    public function process(array $data)
    {
        $helperTools = Mage::helper('swisspost_yellowcube/tools');

        $shipment = $this->salesOrderShipmentFactory->create()->load($data['order_id'], 'order_id');
        $response = $this->getYellowCubeService()->getYCCustomerOrderStatus($data['yc_reference']);

        $this->logger->log(\Monolog\Logger::INFO, print_r($response,true));


        try {
            if (!is_object($response) || !$response->isSuccess()) {
                $message = __('Shipment #%s Status for Order #%s with YellowCube Transaction ID could not received from YellowCube: "%s".',
                    $shipment->getIncrementId(), $data['order_id'], $data['yc_reference'], $response->getStatusText());

                $shipment
                    ->addComment($message, false, false)
                    ->save();

                $this->logger->log(\Monolog\Logger::ERROR, $message."\n".print_r($response,true));
                $helperTools->sendAdminNotification($message);

                $this->resendMessageToQueue($data);

            } else {
                if ($response->isSuccess() && !$response->isPending() && !$response->isError()) {
                    $shipment
                        ->addComment(__('Success ' . $response->getStatusText()), false, false)
                        ->save();
                }
                else if ($response->isError())
                {
                    $shipment
                        ->addComment(__('YellowCube Error: ' . $response->getStatusText()), false, false)
                        ->save();
                }
                else if ($response->isPending())
                {
                    $this->resendMessageToQueue($data);
                }

                if ($this->getHelper()->getDebug()) {
                    $this->logger->log(\Monolog\Logger::DEBUG, print_r($response,true));
                }
            }
        } catch (Exception $e) {

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
            $this->getQueue()->send(\Zend_Json::encode(array(
                'action' => Swisspost_YellowCube_Model_Synchronizer::SYNC_ORDER_UPDATE,
                'order_id' => $data['order_id'],
                'items' => $data['items'],
                'shipment_increment_id' => $data['shipment_increment_id'],
                'yc_reference' => $data['yc_reference'],
                'try' => $data['try'] + 1
            )));
        }
    }
}
