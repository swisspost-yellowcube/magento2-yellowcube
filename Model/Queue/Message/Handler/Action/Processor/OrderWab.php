<?php

/**
 * Liip AG
 *
 * @author      Sylvain Rayé <sylvain.raye at diglin.com>
 * @category    yellowcube
 * @package     Swisspost_yellowcube
 * @copyright   Copyright (c) 2015 Liip AG
 */

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor;

use YellowCube\WAB\AdditionalService\AdditionalShippingServices;
use YellowCube\WAB\AdditionalService\BasicShippingServices;
use YellowCube\WAB\Doc;
use YellowCube\WAB\Order;
use YellowCube\WAB\OrderHeader;
use YellowCube\WAB\Partner;
use YellowCube\WAB\Position;

/**
 * YellowPost WAB processor.
 */
class OrderWab extends \Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorAbstract implements \Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface
{

    /**
     * @var \Magento\Sales\Model\Order\ShipmentFactory
     */
    protected $salesOrderShipmentFactory;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerCustomerFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Swisspost\YellowCube\Helper\Tools
     */
    protected $tools;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $orderRepository;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Swisspost\YellowCube\Helper\Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Sales\Model\Order\ShipmentFactory $salesOrderShipmentFactory,
        \Magento\Customer\Model\CustomerFactory $customerCustomerFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Swisspost\YellowCube\Helper\Tools $tools
    ) {
        parent::__construct($logger, $dataHelper, $clientFactory);
        $this->salesOrderShipmentFactory = $salesOrderShipmentFactory;

        $this->customerCustomerFactory = $customerCustomerFactory;
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->tools = $tools;
    }

    public function process(array $data)
    {

        $order = $this->orderRepository->get($data['order_id']);

        $customerId = null;
        $customer = $this->customerCustomerFactory->create()
            ->setWebsiteId($this->storeManager->getStore($data['store_id'])->getWebsiteId())
            ->loadByEmail($data['partner_email']);

        if ($customer->getId()) {
            $customerId = ' (' . $customer->getId() . ')';
        }

        $partner = new Partner();
        $partner
            ->setPartnerType($data['partner_type'])
            ->setPartnerNo($this->cutString($data['partner_number'], 10))
            ->setPartnerReference(
                $this->cutString($this->dataHelper->getPartnerReference($data['partner_name'], $data['partner_zip_code']), 50)
            )
            ->setName1($this->cutString($data['partner_name']))
            ->setName2($this->cutString($data['partner_name2']))
            ->setStreet($this->cutString($data['partner_street']))
            ->setName3($this->cutString($data['partner_name3']))
            ->setCountryCode($data['partner_country_code'])
            ->setZIPCode($this->cutString($data['partner_zip_code'], 10))
            ->setCity($this->cutString($data['partner_city']))
            ->setEmail($this->cutString($data['partner_email'], 241))
            ->setPhoneNo($this->cutString($data['partner_phone'], 16))
            ->setLanguageCode($this->cutString($data['partner_language'], 2));

        $ycOrder = new Order();
        $ycOrder
            // We use shipment increment id instead of order id for the shop owner User Experience even if the expected parameter should be an OrderID
            ->setOrderHeader(new OrderHeader(
                $this->cutString($data['deposit_number'], 10),
                $this->cutString($order->getIncrementId()),
                $data['order_date']
            ))
            ->setPartnerAddress($partner)
            ->addValueAddedService(new BasicShippingServices($this->cutString($data['service_basic_shipping'])))
            ->addValueAddedService(new AdditionalShippingServices($this->cutString($data['service_additional_shipping'])))
            ->setOrderDocumentsFlag(false);

        // Mime-Type: X(3) pdf oder pcl (kleine Buchstaben)
        // DocTyp: X(2) IV=Invoice/Rechnung, LS=Lieferschein, ZS=ZahlSchein
        // @todo Update PDF stuff.
        $doc = null;
        /*$pdfa = $this->getPdfA($order);
        if ($pdfa) {
            $doc = Doc::fromFile(Doc::DOC_TYPE_LS, Doc::MIME_TYPE_PDF, $pdfa);
            $ycOrder
                ->addOrderDocument($doc)
                ->setOrderDocumentsFlag(true);
        }*/

        foreach ($data['items'] as $key => $item) {
            $position = new Position();
            $position
                ->setPosNo($key + 1)
                ->setArticleNo($this->cutString($item['article_number']))
                ->setEAN($this->cutString($item['article_ean']))
                ->setPlant($this->cutString($data['plant_id'], 4))
                ->setQuantity($item['article_qty'])
                ->setQuantityISO(\YellowCube\ART\UnitsOfMeasure\ISO::PCE)
                ->setShortDescription($this->cutString($item['article_title'], 40));

            $ycOrder->addOrderPosition($position);
        }

        $response = $this->getYellowCubeService()->createYCCustomerOrder($ycOrder);
        try {
            if (!is_object($response) || !$response->isSuccess()) {
                $message = __(
                    'Shipment #%s for Order #%s could not be transmitted to YellowCube: "%s".',
                    $order->getIncrementId(),
                    $data['order_increment_id'],
                    $response->getStatusText()
                );

                $order->addStatusHistoryComment($message)->save();

                $this->logger->log(\Monolog\Logger::ERROR, $message . "\n" . print_r($response, true));
                $this->tools->sendAdminNotification($message);

            // @todo allow the user to send again to yellow cube the request from backend
            } else {
                if ($this->dataHelper->getDebug()) {
                    $this->logger->debug(print_r($ycOrder, true));
                    $this->logger->debug(print_r($response, true));
                }

                /**
                 * Define yc_shipped to 0 to be used later in BAR process that the shipping has not been done
                 */
                reset($data['items']);
                foreach ($order->getItemsCollection() as $item) {
                    /* @var $item Mage_Sales_Model_Order_Shipment_Item */
                    $item
                        ->setAdditionalData(\Zend_Json::encode(['yc_shipped' => 0]))
                        ->save();
                }

                $order
                    ->addComment(__(
                        'Shipment #%s for Order #%s was successfully transmitted to YellowCube. Received reference number %s and status message "%s".',
                        $order->getIncrementId(),
                        $data['order_id'],
                        $response->getReference(),
                        $response->getStatusText()
                    ), false, false)
                    ->save();

                // WAR Message
                $this->getQueue()->send(\Zend_Json::encode([
                    'action' => \Swisspost\YellowCube\Model\Synchronizer::SYNC_ORDER_UPDATE,
                    'order_id' => $data['order_id'],
                    'shipment_increment_id' => $order->getIncrementId(),
                    'yc_reference' => $response->getReference()
                ]));
            }
        } catch (\Exception $e) {
            // Let's keep going further processes
            $this->logger->critical($e);
        }

        return $this;
    }

    /**
     * @param \Magento\Sales\Model\Order $shipment
     * @return mixed
     */
    public function getPdfA(\Magento\Sales\Model\Order $shipment)
    {
        /** @var Swisspost_YellowCube_Model_Sales_Order_Pdf_Shipment $shipmentPdfGenerator */
        $shipmentPdfGenerator = Mage::getModel('swisspost_yellowcube/sales_order_pdf_shipment');
        return $shipmentPdfGenerator->getPdf($shipment);
    }
}