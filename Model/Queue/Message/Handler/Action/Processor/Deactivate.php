<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action\Processor;

use Swisspost\YellowCube\Model\Queue\Message\Handler\Action\ProcessorInterface;

class Bar
  extends Insert
  implements ProcessorInterface
{
    protected $_changeFlag = \YellowCube\ART\ChangeFlag::DEACTIVATE;
}
