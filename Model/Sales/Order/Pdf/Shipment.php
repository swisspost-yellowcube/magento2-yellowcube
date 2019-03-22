<?php

namespace Swisspost\YellowCube\Model\Sales\Order\Pdf;


class Shipment
{
    protected $_pdfStylesTemplatePath = 'styles.html';
    protected $_pdfMainTemplatePath = 'main.html';
    protected $_pdfProductTemplatePath = 'product.html';

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTimeDateTime;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Sales\Helper\Data
     */
    protected $salesHelper;

    /**
     * @var \Magento\Payment\Helper\Data
     */
    protected $paymentHelper;

    public function __construct(
        \Magento\Framework\Stdlib\DateTime\DateTime $dateTimeDateTime,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Sales\Helper\Data $salesHelper,
        \Magento\Payment\Helper\Data $paymentHelper
    ) {
        $this->dateTimeDateTime = $dateTimeDateTime;
        $this->scopeConfig = $scopeConfig;
        $this->salesHelper = $salesHelper;
        $this->paymentHelper = $paymentHelper;
    }
    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return string
     */
    public function getPdf(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        if ($shipment->getStoreId()) {
            Mage::app()->getLocale()->emulate($shipment->getStoreId());
            Mage::app()->setCurrentStore($shipment->getStoreId());
        }

        $map = $this->buildMap($shipment);
        $stylesTemplate = $this->getTemplateFileContents($this->_pdfStylesTemplatePath);
        $mainTemplate = $this->getTemplateFileContents($this->_pdfMainTemplatePath);
        $mainTemplate = $this->applyMap($mainTemplate, $map);

        $html = $stylesTemplate . $mainTemplate;

        if ($shipment->getStoreId()) {
            Mage::app()->getLocale()->revert();
        }

        return $this->generatePdf($html, Mage::getBaseDir('tmp') . DS . $this->getFileName() . '.pdf');
    }

    /**
     * @param string $content
     * @param string $destination
     * @return string
     */
    public function generatePdf($content, $destination)
    {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, true);

        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetTitle($this->getFileName());

        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $pdf->setFontSubsetting(true);
        $pdf->SetFont('helvetica', '', 9, '', true);
        $pdf->AddPage();

        $pdf->writeHTMLCell(0, 0, '', '', $content, 0, 1, 0, true, '', true);

        $pdf->Output($destination, 'F');
        return $destination;
    }

    /**
     * @return string
     */
    public function getFileName()
    {
        return 'packingslip_' . $this->dateTimeDateTime->date('Y-m-d_H-i-s');
    }

    /**
     * @param string $source
     * @param array $map
     * @return string
     */
    public function applyMap($source, array $map)
    {
        foreach($map as $key => $value) {
            $source = str_replace('{{' . $key . '}}', $value, $source);
        }

        return $source;
    }

    /**
     * @param \Magento\Sales\Model\Order\Shipment $shipment
     * @return array
     */
    public function buildMap(\Magento\Sales\Model\Order\Shipment $shipment)
    {
        $map = array(
            'packingslip_id_label' => __('Packingslip # '),
            'order_id_label' => __('Order # '),
            'order_date_label' => __('Order Date: '),
            'billing_address_label' => __('Sold to:'),
            'shipping_address_label' => __('Ship to:'),
            'payment_method_label' => __('Payment Method:'),
            'shipping_method_label' => __('Shipping Method:'),
            'products_qty_label' => __('Qty'),
            'products_label' => __('Products'),
            'products_sku_label' => __('SKU'),
        );

        $order = $shipment->getOrder();
        $billingAddress = $order->getBillingAddress();

        $map['packingslip_id'] = $shipment->getIncrementId();
        $map['order_id'] = $this->scopeConfig->isSetFlag(
            \Magento\Sales\Model\Order\Pdf\Shipment::XML_PATH_SALES_PDF_SHIPMENT_PUT_ORDER_ID, \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $order->getStoreId()
        )
            ? $order->getRealOrderId()
            : 'N/A';

        $map['order_date'] = $this->getCoreHelper()->formatDate($order->getCreatedAtStoreDate(), 'medium', false);

        $map['billing_name'] = $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname();
        $map['billing_street'] = $billingAddress->getStreetFull();
        $map['billing_city'] = $billingAddress->getCity();
        $map['billing_zip'] = $billingAddress->getPostcode();
        $map['billing_country'] = $billingAddress->getCountryModel()->getIso2Code();
        $map['billing_phone'] = $billingAddress->getTelephone();

        $shippingAddress = $order->getShippingAddress();

        $map['shipping_name'] = $shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname();
        $map['shipping_street'] = $shippingAddress->getStreetFull();
        $map['shipping_city'] = $shippingAddress->getCity();
        $map['shipping_zip'] = $shippingAddress->getPostcode();
        $map['shipping_country'] = $shippingAddress->getCountry();
        $map['shipping_phone'] = $shippingAddress->getTelephone();

        $map['payment_method'] = $paymentInfo = $this->getPaymentHelper()->getInfoBlock($order->getPayment())
            ->setIsSecureMode(true)
            ->toHtml();
        $map['shipping_method'] = $order->getShippingDescription();
        $map['shipping_charges'] = '(' . __('Total Shipping Charges') . ' '
            . $order->formatPriceTxt($order->getShippingAmount()) . ')';

        $map['products_data'] = '';
        foreach ($shipment->getAllItems() as $item) {
            if ($item->getOrderItem()->getParentItem()) {
                continue;
            }

            $map['products_data'] .= $this->drawItem($item, $order);
        }

        return $map;
    }

    /**
     * @return string
     */
    public function drawItem($item)
    {
        $sku = ($item->getOrderItem()->getProductOptionByCode('simple_sku'))
            ? $item->getOrderItem()->getProductOptionByCode('simple_sku')
            : $item->getSku();

        $map = array(
            'qty' => $item->getQty() * 1,
            'title' => $item->getName(),
            'sku' => $sku
        );
        $productTemplate = $this->getTemplateFileContents($this->_pdfProductTemplatePath);
        $productTemplate = $this->applyMap($productTemplate, $map);
        return $productTemplate;
    }

    /**
     * @param string $templateFilename
     * @return string
     */
    public function getTemplateFileContents($templateFilename)
    {
        $pathParts = array(
            Mage::getConfig()->getModuleDir('etc', 'Swisspost_YellowCube'),
            'order',
            'shipment',
            'pdf',
            'template',
            $templateFilename
        );

        $configFile = implode(DS, $pathParts);
        return file_get_contents($configFile);
    }

    /**
     * @return \Magento\Sales\Helper\Data
     */
    public function getSalesHelper()
    {
        return $this->salesHelper;
    }

    /**
     * @return Mage_Core_Helper_Data
     */
    public function getCoreHelper()
    {
        return Mage::helper('core');
    }

    /**
     * @return \Magento\Payment\Helper\Data
     */
    public function getPaymentHelper()
    {
        return Mage::helper('payment');
    }
}
