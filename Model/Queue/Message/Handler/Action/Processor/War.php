<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor;

use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;

class War
  extends ProcessorAbstract
  implements ProcessorInterface
{

    /**
     * @var \Magento\Sales\Model\Order\ShipmentFactory
     */
    protected $salesOrderShipmentFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Sales\Model\Order\Shipment\TrackFactory
     */
    protected $salesOrderShipmentTrackFactory;

    public function __construct(
        \Magento\Sales\Model\Order\ShipmentFactory $salesOrderShipmentFactory,
        \Psr\Log\LoggerInterface $logger,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $salesOrderShipmentTrackFactory
    ) {
        $this->salesOrderShipmentFactory = $salesOrderShipmentFactory;
        $this->logger = $logger;
        $this->salesOrderShipmentTrackFactory = $salesOrderShipmentTrackFactory;
    }
    /**
     * @param array $data
     * @return $this
     */
    public function process(array $data)
    {
        try {
            $goodsIssueList = $this->getYellowCubeService()->getYCCustomerOrderReply();

            foreach ($goodsIssueList as $goodsIssue) {
                $header = $goodsIssue->getCustomerOrderHeader();

                $shipment = $this->salesOrderShipmentFactory->create()->load($header->getCustomerOrderNo(), 'increment_id');
                $shipmentNo = $header->getPostalShipmentNo();

                // Multi packaging / shipping is not supported atm.
                if (!empty($shipmentNo) && $shipment->getId()) {
                    /**
                     * Define yc_shipped to 1 to be used later in BAR process that the shipping has been done
                     */
                    $customerOrderDetails = $goodsIssue->getCustomerOrderList();
                    $shipmentItems = $shipment->getItemsCollection();
                    $hash = array();

                    try {
                        foreach ($customerOrderDetails as $customerOrderDetail) {
                            $this->logger->log(\Monolog\Logger::DEBUG, 'Debug $customerOrderDetail '.print_r($customerOrderDetail,true));

                            reset($shipmentItems);
                            foreach ($shipmentItems as $item) {

                                $this->logger->log(\Monolog\Logger::DEBUG, 'Debug $item '.print_r($item,true));

                                /* @var $item Mage_Sales_Model_Order_Shipment_Item */
                                if ($customerOrderDetail->getArticleNo() == $item->getSku() && !isset($hash[$item->getId()])) {
                                    $item
                                        ->setAdditionalData(\Zend_Json::encode(array('yc_shipped' => 1)))
                                        ->save();
                                    $hash[$item->getId()] = true;
                                }
                            }
                            $lotId = $customerOrderDetail->getLot();
                            $quantityUOM = $customerOrderDetail->getQuantityUOM();
                        }
                    } catch (Exception $e) {
                        $this->logger->critical($e);
                    }

                    $this->logger->log(\Monolog\Logger::DEBUG, $this->getHelper()->__('Items for shipment %s considered as shipped',$shipment->getIncrementId()));

                    // shipping number contains a semicolon, post api supports multiple values
                    $shippingUrl = 'http://www.post.ch/swisspost-tracking?formattedParcelCodes=' . $shipmentNo;

                    // Add a message to the order history incl. link to shipping infos
                    $message = $this->getHelper()->__('Your order has been shipped. You can use the following url for shipping tracking: <a href="%1$s" target="_blank">%1$s</a>', $shippingUrl);
                    $message .= "\r\n" . $this->getHelper()->__('Lot ID: %s', $lotId);
                    $message .= "\r\n" . $this->getHelper()->__('Quantity UOM: %s', $quantityUOM);

                    $track = $this->salesOrderShipmentTrackFactory->create();
                    $track
                        ->setCarrierCode($shipment->getOrder()->getShippingCarrier()->getCarrierCode())
                        ->setTitle($this->getHelper()->__('SwissPost Tracking Code'))
                        ->setNumber($shippingUrl);

                    $shipment
                        ->addTrack($track)
                        ->addComment($this->getHelper()->__($message), true, true)
                        ->save();

                    $shipment->sendEmail(true, $message);

                    $this->logger->log(\Monolog\Logger::DEBUG, $this->getHelper()->__('Shipment %s comment added and email sent',$shipment->getIncrementId()));
                }
            }

            if ($this->getHelper()) {
                $this->logger->log(\Monolog\Logger::DEBUG, print_r($goodsIssueList,true));
            }
        } catch (Exception $e) {
            // Let's keep going further processes
            $this->logger->critical($e);
        }

        return $this;
    }
}
