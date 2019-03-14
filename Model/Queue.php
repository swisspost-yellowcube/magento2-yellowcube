<?php

namespace Swisspost\YellowCube\Model;


class Queue
{
    const DEFAULT_NAME = 'default';

    /**
     * @var \Magento\Framework\App\ResourceConnectionFactory
     */
    protected $resourceConnectionFactory;

    public function __construct(
        \Magento\Framework\App\ResourceConnectionFactory $resourceConnectionFactory
    ) {
        $this->resourceConnectionFactory = $resourceConnectionFactory;
    }
    public function getInstance()
    {
        $adapter = $this->resourceConnectionFactory->create()->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_WRITE_RESOURCE);
        return new \Zend_Queue('Db', array(
            'dbAdapter' => $adapter,
            'name' => self::DEFAULT_NAME
        ));
    }
}
