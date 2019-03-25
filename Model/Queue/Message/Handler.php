<?php

namespace Swisspost\YellowCube\Model\Queue\Message;

use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;

/**
 * Message queue consumer that forwards to the respective action class.
 */
class Handler
{

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    protected $jsonSerializer;

    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    public function __construct(
        Json $jsonSerializer,
        ObjectManagerInterface $objectManager
    ) {
        $this->jsonSerializer = $jsonSerializer;
        $this->objectManager = $objectManager;
    }

    /**

     * @param string $data
     * @throws \Exception
     */
    public function process(string $data)
    {
        $data = $this->jsonSerializer->unserialize($data);
        // @todo Refactor this, use full class name in action, virtual types?
        $processor = $this->objectManager->get('Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor\\' . ucfirst($data['action']));
        if ($processor instanceof ProcessorInterface) {
            $processor->process($data);
            return;
        }
        throw new \Exception(
            get_class($processor)
            . ' doesn\'t implement ' . ProcessorInterface::class
        );
    }
}
