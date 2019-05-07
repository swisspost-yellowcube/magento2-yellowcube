<?php

namespace Swisspost\YellowCube\Controller\Adminhtml\Sync;

use Swisspost\YellowCube\Model\Synchronizer;

class Download extends \Magento\Backend\App\Action implements \Magento\Framework\App\ActionInterface
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Swisspost\YellowCube\Model\Synchronizer
     */
    protected $synchronizer;

    /**
     * @var \Magento\Framework\Controller\Result\JsonFactory
     */
    protected $jsonResultFactory;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger,
        Synchronizer $synchronizer,
        \Magento\Framework\Controller\Result\JsonFactory $jsonResultFactory
    ) {
        parent::__construct(
            $context
        );
        $this->logger = $logger;
        $this->synchronizer = $synchronizer;
        $this->jsonResultFactory = $jsonResultFactory;
    }

    public function execute()
    {
        $response = $this->jsonResultFactory->create();
        try {
            $this->synchronizer->bar();
            $response->setData(1);
        } catch (\Exception $e) {
            $this->logger->critical($e);
            $response->setData(0);
        }
        return $response;
    }

    public function uploadAction()
    {
        try {
            // @todo care of the current website ID used
            $this->getSynchronizer()->updateAll();
            $this->logger->debug('Swisspost Sync upload');
            echo 1;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            echo 0;
        }
    }
}
