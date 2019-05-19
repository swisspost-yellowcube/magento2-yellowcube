<?php

namespace Swisspost\YellowCube\Ui\Component\Listing\Columns;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * @api
 * @since 100.0.2
 */
class YellowCube extends \Magento\Ui\Component\Listing\Columns\Column
{
    /**
     * Column name
     */
    const NAME = 'yellowcube';

    /**
     * Store manager
     *
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param StoreManagerInterface $storeManager
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        StoreManagerInterface $storeManager,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
        $this->storeManager = $storeManager;
    }

    /**
     * {@inheritdoc}
     * @deprecated 101.0.0
     */
    public function prepareDataSource(array $dataSource)
    {
        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $sync = $item['yc_sync_with_yellowcube'] ?? false;
                $reference = $item['yc_reference'] ?? null;
                $response = $item['yc_response'] ?? null;

                $status = __('No');
                $cssClass = '';
                if ($sync) {
                    if ($reference) {
                        $status = __('Pending, Reference: %1', $reference);
                    } else {
                        $status = __('Yes');
                        $cssClass = 'yellowcube-success';
                    }
                } elseif ($response) {
                    $status = __('Error: %1', $response);
                    $cssClass = 'yellowcube-error';
                }

                $item['fieldClass'] = $cssClass;
                $item['yellowcube'] = $status;
            }
        }

        return $dataSource;
    }

    /**
     * Prepare component configuration
     * @return void
     */
    public function prepare()
    {
        parent::prepare();

        $this->getContext()->getDataProvider()->addField('yc_reference');
        $this->getContext()->getDataProvider()->addField('yc_response');
    }
}
