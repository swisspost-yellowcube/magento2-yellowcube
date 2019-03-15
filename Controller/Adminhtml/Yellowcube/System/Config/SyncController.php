<?php

namespace Swisspost\YellowCube\Controller\Adminhtml\System\Config;

class SyncController extends \Magento\Backend\App\Action
{

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        \Magento\Backend\App\Action\Context $context,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->logger = $logger;
        parent::__construct(
            $context
        );
    }

    public function downloadAction()
    {
        try {
            // @todo
            //$this->getSynchronizer()->updateAll();
            $this->logger->debug('Swisspost Sync download');
            echo 1;
        } catch (\Exception $e) {
            $this->logger->critical($e);
            echo 0;
        }
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

    /**
     * @return Swisspost_YellowCube_Model_Synchronizer
     */
    public function getSynchronizer()
    {
        return Mage::getSingleton('swisspost_yellowcube/synchronizer');
    }

    public function execute() {
      // TODO: Implement execute() method.
    }

}
