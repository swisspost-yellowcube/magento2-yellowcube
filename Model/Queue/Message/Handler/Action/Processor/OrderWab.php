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

use Swisspost\YellowCube\Helper\Data;
use Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository;
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
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Sales\Model\OrderRepository
     */
    protected $orderRepository;

    /**
     * @var \Magento\Sales\Model\Order\ShipmentRepository
     */
    protected $shipmentRepository;

    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    protected $productRepository;

    /**
     * @var \Swisspost\YellowCube\Model\YellowCubeShipmentItemRepository
     */
    protected $yellowCubeShipmentItemRepository;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Swisspost\YellowCube\Helper\Data $dataHelper,
        \Swisspost\YellowCube\Model\Library\ClientFactory $clientFactory,
        \Magento\Sales\Model\OrderRepository $orderRepository,
        \Magento\Customer\Model\CustomerFactory $customerCustomerFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Sales\Model\Order\ShipmentRepository $shipmentRepository,
        \Magento\Catalog\Model\ProductRepository $productRepository,
        YellowCubeShipmentItemRepository $yellowCubeShipmentItemRepository
    ) {
        parent::__construct($logger, $dataHelper, $clientFactory);
        $this->storeManager = $storeManager;
        $this->orderRepository = $orderRepository;
        $this->shipmentRepository = $shipmentRepository;
        $this->productRepository = $productRepository;
        $this->yellowCubeShipmentItemRepository = $yellowCubeShipmentItemRepository;
    }

    public function process(array $data)
    {

        /** @var \Magento\Sales\Model\Order\Shipment $shipment */
        $shipment = $this->shipmentRepository->get($data['shipment_id']);
        $order = $this->orderRepository->get($shipment->getOrderId());

        $shippingAddress = $shipment->getShippingAddress();

        $partner = $this->createPartner($shipment, $shippingAddress);

        $shipping_method = str_replace('yellowcube_', '', $order->getShippingMethod());

        $ycOrder = new Order();
        $ycOrder
            // We use shipment increment id instead of order id for the shop owner User Experience even if the expected parameter should be an OrderID
            ->setOrderHeader(new OrderHeader(
                $this->cutString($this->dataHelper->getDepositorNumber($shipment->getStoreId()), 10),
                $this->cutString($order->getIncrementId()),
                date('Ymd')
            ))
            ->setPartnerAddress($partner)
            ->addValueAddedService(new BasicShippingServices($this->cutString($this->dataHelper->getRealCode($shipping_method))))
            ->addValueAddedService(new AdditionalShippingServices($this->cutString($this->dataHelper->getAdditionalShipping($shipping_method))))
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

        /** @var \Magento\Sales\Api\Data\ShipmentItemInterface $item */
        foreach ($shipment->getAllItems() as $key => $item) {
            $product = $this->productRepository->getById($item->getProductId());
            $position = new Position();
            $position
                ->setPosNo($key + 1)
                ->setArticleNo($this->cutString($item->getSku()))
                ->setEAN($this->cutString($product->getData('yc_ean_code')))
                ->setPlant($this->cutString($this->dataHelper->getPlantId(), 4))
                ->setQuantity($this->cutString($item->getQty(), 4))
                ->setQuantityISO(\YellowCube\ART\UnitsOfMeasure\ISO::PCE)
                ->setShortDescription($this->cutString($item->getName(), 40));
            $ycOrder->addOrderPosition($position);
        }

        try {
            $response = $this->getYellowCubeService()->createYCCustomerOrder($ycOrder);
            if (!$response->isSuccess()) {
                $message = __(
                    'Shipment #%1 for Order #%2 could not be transmitted to YellowCube: "%3".',
                    $shipment->getIncrementId(),
                    $order->getIncrementId(),
                    $response->getStatusText()
                );

                $shipment->addComment($message);
                $this->shipmentRepository->save($shipment);

                $this->logger->error($message . "\n" . print_r($response, true));

            // @todo allow the user to send again to yellow cube the request from backend
            } else {
                if ($this->dataHelper->getDebug()) {
                    $this->logger->debug(print_r($ycOrder, true));
                    $this->logger->debug(print_r($response, true));
                }

                // Define yc_shipped to 0 to be used later in BAR process that the shipping has not been done.
                foreach ($shipment->getItems() as $item) {
                    $this->yellowCubeShipmentItemRepository->insertShipmentItem($item, $response->getReference());
                }

                // Update the shipment.
                $shipment->addComment(__(
                    'Shipment #%1 for Order #%2 was successfully transmitted to YellowCube. Received reference number %3 and status message "%4".',
                    $shipment->getIncrementId(),
                    $order->getIncrementId(),
                    $response->getReference(),
                    $response->getStatusText()
                ), false, false);
                $this->shipmentRepository->save($shipment);
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

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @param \Magento\Sales\Model\Order\Address $shippingAddress
     * @return Partner
     */
    protected function createPartner(
        \Magento\Sales\Model\Order\Shipment $shipment,
        \Magento\Sales\Model\Order\Address $shippingAddress
    ): Partner {
        $locale = $this->dataHelper->getConfigValue('general/locale/code', $shipment->getStoreId());
        $locale = explode('_', $locale);

        $partner = new Partner();
        $partner
            ->setPartnerType(Data::PARTNER_TYPE)
            ->setPartnerNo($this->cutString($this->dataHelper->getPartnerNumber($shipment->getStoreId()), 10))
            ->setPartnerReference(
                $this->cutString($this->dataHelper->getPartnerReference(
                    $shippingAddress->getName(),
                    $shippingAddress->getPostcode()
                ), 50)
            )
            ->setName1($this->cutString($shippingAddress->getName()))
            ->setName2($this->cutString($shippingAddress->getCompany()))
            ->setStreet($this->cutString($shippingAddress->getStreetLine(1)))
            ->setName3($this->cutString($shippingAddress->getStreetLine(2)))
            ->setCountryCode($shippingAddress->getCountryId())
            ->setZIPCode($this->cutString($shippingAddress->getPostcode(), 10))
            ->setCity($this->cutString($shippingAddress->getCity()))
            ->setEmail($this->cutString($shippingAddress->getEmail(), 241))
            ->setPhoneNo($this->cutString($shippingAddress->getTelephone(), 16))
            ->setLanguageCode($this->cutString($locale[0], 2));
        return $partner;
    }
}
