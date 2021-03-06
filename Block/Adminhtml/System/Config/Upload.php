<?php

namespace Swisspost\YellowCube\Block\Adminhtml\System\Config;


class Upload extends \Magento\Config\Block\System\Config\Form\Field
{

    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    protected $backendUrlInterface;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Model\UrlInterface $backendUrlInterface,
        array $data = []
    ) {
        $this->backendUrlInterface = $backendUrlInterface;
        parent::__construct(
            $context,
            $data
        );
    }

    /**
     * Set template to itself
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate('system/config/synchronize.phtml');
        }
        return $this;
    }

    /**
     * Unset some non-related element parameters
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }

    /**
     * Get the button and scripts contents
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $originalData = $element->getOriginalData();
        $this->addData([
            'button_label' => __($originalData['button_label']),
            'html_id' => $element->getHtmlId(),
            'ajax_url' => $this->backendUrlInterface->getUrl('yellowcube/sync/upload')
        ]);

        return $this->_toHtml();
    }
}
