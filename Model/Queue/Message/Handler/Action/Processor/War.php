<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor;

use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;

class War extends ProcessorAbstract implements ProcessorInterface
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
        \Psr\Log\LoggerInterface $logger,
        \Swisspost\YellowCube\Helper\Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory,
        \Magento\Sales\Model\Order\ShipmentFactory $salesOrderShipmentFactory,
        \Magento\Sales\Model\Order\Shipment\TrackFactory $salesOrderShipmentTrackFactory
    ) {
        parent::__construct($logger, $dataHelper, $clientFactory);
        $this->salesOrderShipmentFactory = $salesOrderShipmentFactory;
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
                    $hash = [];

                    try {
                        foreach ($customerOrderDetails as $customerOrderDetail) {
                            $this->logger->debug('Debug $customerOrderDetail ' . print_r($customerOrderDetail, true));

                            reset($shipmentItems);
                            foreach ($shipmentItems as $item) {
                                $this->logger->debug('Debug $item ' . print_r($item, true));

                                /* @var $item Mage_Sales_Model_Order_Shipment_Item */
                                if ($customerOrderDetail->getArticleNo() == $item->getSku() && !isset($hash[$item->getId()])) {
                                    $item
                                        ->setAdditionalData(\Zend_Json::encode(['yc_shipped' => 1]))
                                        ->save();
                                    $hash[$item->getId()] = true;
                                }
                            }
                            $lotId = $customerOrderDetail->getLot();
                            $quantityUOM = $customerOrderDetail->getQuantityUOM();
                        }
                    } catch (\Exception $e) {
                        $this->logger->critical($e);
                    }

                    $this->logger->debug(__('Items for shipment %s considered as shipped', $shipment->getIncrementId()));

                    // shipping number contains a semicolon, post api supports multiple values
                    $shippingUrl = 'http://www.post.ch/swisspost-tracking?formattedParcelCodes=' . $shipmentNo;

                    // Add a message to the order history incl. link to shipping infos
                    $message = __('Your order has been shipped. You can use the following url for shipping tracking: <a href="%1$s" target="_blank">%1$s</a>', $shippingUrl);
                    $message .= "\r\n" . __('Lot ID: %s', $lotId);
                    $message .= "\r\n" . __('Quantity UOM: %s', $quantityUOM);

                    $track = $this->salesOrderShipmentTrackFactory->create();
                    $track
                        ->setCarrierCode($shipment->getOrder()->getShippingCarrier()->getCarrierCode())
                        ->setTitle(__('SwissPost Tracking Code'))
                        ->setNumber($shippingUrl);

                    $shipment
                        ->addTrack($track)
                        ->addComment(__($message), true, true)
                        ->save();

                    $shipment->sendEmail(true, $message);

                    $this->logger->debug(__('Shipment %s comment added and email sent', $shipment->getIncrementId()));
                }
            }

            if ($this->dataHelper->getDebug()) {
                $this->logger->debug(print_r($goodsIssueList, true));
            }
        } catch (\Exception $e) {
            // Let's keep going further processes
            $this->logger->critical($e);
        }

        return $this;
    }
}
