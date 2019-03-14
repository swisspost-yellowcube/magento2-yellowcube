<?php

namespace Swisspost\YellowCube\Model\Queue\Message;

/**
 * Class Swisspost_YellowCube_Model_Queue_Processor
 */
class Processor
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }
    /**
     * Called by cron task
     *
     * @throws \Exception
     * @throws \Zend_Json_Exception
     * @throws \Zend_Queue_Exception
     */
    public function process()
    {
        /** @var \Zend_Queue $queue */
        $queue = Mage::getModel('swisspost_yellowcube/queue')->getInstance();
        foreach ($queue->receive(100) as $message) {

            if (Mage::helper('swisspost_yellowcube')->getDebug()) {
                $this->logger->log(\Monolog\Logger::DEBUG, $message->body);
            }

            try {
                Mage::getSingleton('swisspost_yellowcube/queue_message_handler')->process(
                    \Zend_Json::decode($message->body)
                );
            } catch (\Exception $e) {
                $this->logger->critical($e);
            }

            $queue->deleteMessage($message);
        }
    }
}
