<?php

namespace Swisspost\YellowCube\Model\Queue\Message\Handler\Action;

interface ProcessorInterface
{
    /**
     * @param array $data
     * @return void
     */
    public function process(array $data);
}
