<?php
/**
 * Diglin GmbH - Switzerland
 *
 * @author      Sylvain Rayé <support at diglin.com>
 * @category    yellowcube
 * @copyright   Copyright (c) 2014 Diglin GmbH (http://www.diglin.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Swisspost\YellowCube\Helper;

/**
 * Class Swisspost_YellowCube_Helper_Tools
 */
class Tools extends \Magento\Framework\App\Helper\AbstractHelper
{
    const XML_PATH_EMAIL_NOTIFICATION_TEMPLATE = 'system/messages/notification_email_template';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Customer\Model\CustomerFactory
     */
    protected $customerCustomerFactory;

    /**
     * @var \Magento\Framework\TranslateInterface
     */
    protected $translateInterface;

    /**
     * @var \Magento\Email\Model\TemplateFactory
     */
    protected $emailTemplateFactory;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Customer\Model\CustomerFactory $customerCustomerFactory,
        \Magento\Framework\TranslateInterface $translateInterface,
        \Magento\Email\Model\TemplateFactory $emailTemplateFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
        $this->customerCustomerFactory = $customerCustomerFactory;
        $this->translateInterface = $translateInterface;
        $this->emailTemplateFactory = $emailTemplateFactory;
        $this->logger = $logger;
        parent::__construct(
            $context
        );
    }


    /**
     * @param $message
     */
    public static function sendAdminNotification($message)
    {
        self::sendNotification(
            $this->scopeConfig->getValue(Mage_Log_Model_Cron::XML_PATH_EMAIL_LOG_CLEAN_IDENTITY, \Magento\Store\Model\ScopeInterface::SCOPE_STORE), //  e.g. 'general'
            'support',
            $this->scopeConfig->getValue(self::XML_PATH_EMAIL_NOTIFICATION_TEMPLATE, \Magento\Store\Model\ScopeInterface::SCOPE_STORE),
            array('message' => $message),
            $this->storeManager->getStore()->getId()
        );
    }

    /**
     * Send a notification email to the customer or the shop managers
     *
     * @param string $sender
     * @param string $recipient
     * @param string $template
     * @param array $variables
     * @param int $storeId
     * @throw \Exception
     */
    public static function sendNotification($sender = 'general', $recipient = 'customer', $template, $variables = array(), $storeId = null)
    {
        try {
            if ($recipient == 'customer') {
                $customer = $variables['customer'];
                if (is_numeric($customer)) {
                    $customer = $this->customerCustomerFactory->create()->load($customer);
                }
                $recipient = array('name' => $customer->getName(), 'email' => $customer->getEmail());
            } else {
                $recipient = array(
                    'name' => $this->scopeConfig->getValue('trans_email/ident_' . $recipient . '/name', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId),
                    'email' => $this->scopeConfig->getValue('trans_email/ident_' . $recipient . '/email', \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $storeId)
                );
            }
            $translate = $this->translateInterface;
            $translate->setTranslateInline(false);
            $emailTemplate = $this->emailTemplateFactory->create();
            $emailTemplate->setDesignConfig(array('area' => 'frontend', 'store' => $storeId))
                ->sendTransactional($template, // xml path email template
                    $sender,
                    $recipient['email'],
                    $recipient['name'],
                    $variables,
                    $storeId);
            $translate->setTranslateInline(true);
        } catch (Exception $e) {
            $this->logger->critical($e);
            self::sendAdminNotification($e->__toString());
        }
    }
}
