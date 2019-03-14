<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler;


use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;

class Handler
{
    public function process(array $data)
    {
        /** @var Swisspost_YellowCube_Model_Queue_Message_Handler_Action_ProcessorInterface $processor */
        $processor = Mage::getModel('swisspost_yellowcube/queue_message_handler_action_processor_' . $data['action']);
        if ($processor instanceof ProcessorInterface) {
            $processor->process($data);
            return;
        }
        throw new \Exception(
            get_class($processor)
            . ' doesn\'t implement Swisspost_YellowCube_Model_Queue_Message_Handler_Action_ProcessorInterface'
        );
    }
}
