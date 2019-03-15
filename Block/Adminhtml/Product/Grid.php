<?php

namespace Swisspost\YellowCube\Block\Adminhtml\Product;


class Grid extends \Magento\Catalog\Block\Adminhtml\Product\Grid
{

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $catalogResourceModelProductCollectionFactory;

    /**
     * @var \Magento\Catalog\Helper\Data
     */
    protected $catalogHelper;

    /**
     * @var \Magento\Catalog\Model\Product\Type
     */
    protected $catalogProductType;

    /**
     * @var \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory
     */
    protected $eavResourceModelEntityAttributeSetCollectionFactory;

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    protected $catalogProductFactory;

    /**
     * @var \Magento\Catalog\Model\Product\VisibilityFactory
     */
    protected $catalogProductVisibilityFactory;

    /**
     * @var \Magento\Catalog\Model\Product\Attribute\Source\Status
     */
    protected $catalogProductAttributeSourceStatus;

    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Backend\Helper\Data $backendHelper,
        \Magento\Store\Model\WebsiteFactory $websiteFactory,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setsFactory,
        \Magento\Catalog\Model\ProductFactory $productFactory,
        \Magento\Catalog\Model\Product\Type $type,
        \Magento\Catalog\Model\Product\Attribute\Source\Status $status,
        \Magento\Catalog\Model\Product\Visibility $visibility,
        \Magento\Framework\Module\Manager $moduleManager,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $catalogResourceModelProductCollectionFactory,
        \Magento\Catalog\Helper\Data $catalogHelper,
        \Magento\Catalog\Model\Product\Type $catalogProductType,
        \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $eavResourceModelEntityAttributeSetCollectionFactory,
        \Magento\Catalog\Model\ProductFactory $catalogProductFactory,
        \Magento\Catalog\Model\Product\VisibilityFactory $catalogProductVisibilityFactory,
        \Magento\Catalog\Model\Product\Attribute\Source\Status $catalogProductAttributeSourceStatus,
        array $data = []
    ) {
        $this->catalogResourceModelProductCollectionFactory = $catalogResourceModelProductCollectionFactory;
        $this->catalogHelper = $catalogHelper;
        $this->catalogProductType = $catalogProductType;
        $this->eavResourceModelEntityAttributeSetCollectionFactory = $eavResourceModelEntityAttributeSetCollectionFactory;
        $this->catalogProductFactory = $catalogProductFactory;
        $this->catalogProductVisibilityFactory = $catalogProductVisibilityFactory;
        $this->catalogProductAttributeSourceStatus = $catalogProductAttributeSourceStatus;
        parent::__construct(
            $context,
            $backendHelper,
            $websiteFactory,
            $setsFactory,
            $productFactory,
            $type,
            $status,
            $visibility,
            $moduleManager,
            $data
        );
    }

    protected function _prepareCollection()
    {
        $store = $this->_getStore();
        $collection = $this->catalogResourceModelProductCollectionFactory->create()->addAttributeToSelect('sku')
            ->addAttributeToSelect('yc_most_recent_expiration_date')
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('attribute_set_id')
            ->addAttributeToSelect('type_id');

        //die(var_dump($collection));

        if ($this->catalogHelper->isModuleEnabled('Mage_CatalogInventory')) {
            $collection->joinField('qty',
                'cataloginventory/stock_item',
                'qty',
                'product_id=entity_id',
                '{{table}}.stock_id=1',
                'left');
        }
        if ($store->getId()) {
            //$collection->setStoreId($store->getId());
            $adminStore = Mage_Core_Model_App::ADMIN_STORE_ID;
            $collection->addStoreFilter($store);
            $collection->joinAttribute(
                'name',
                'catalog_product/name',
                'entity_id',
                null,
                'inner',
                $adminStore
            );
            $collection->joinAttribute(
                'custom_name',
                'catalog_product/name',
                'entity_id',
                null,
                'inner',
                $store->getId()
            );
            $collection->joinAttribute(
                'status',
                'catalog_product/status',
                'entity_id',
                null,
                'inner',
                $store->getId()
            );

            $collection->joinAttribute(
                'visibility',
                'catalog_product/visibility',
                'entity_id',
                null,
                'inner',
                $store->getId()
            );
            $collection->joinAttribute(
                'price',
                'catalog_product/price',
                'entity_id',
                null,
                'left',
                $store->getId()
            );
        }
        else {
            $collection->addAttributeToSelect('price');
            $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');
        }

        $this->setCollection($collection);

        //parent::_prepareCollection();
        $this->getCollection()->addWebsiteNamesToResult();
        return $this;
    }

    protected function _prepareColumns()
    {
        $this->addColumn('entity_id',
            array(
                'header'=> __('ID'),
                'width' => '50px',
                'type'  => 'number',
                'index' => 'entity_id',
            ));
        $this->addColumn('name',
            array(
                'header'=> __('Name'),
                'index' => 'name',
            ));

        $store = $this->_getStore();
        if ($store->getId()) {
            $this->addColumn('custom_name',
                array(
                    'header'=> __('Name in %s', $store->getName()),
                    'index' => 'custom_name',
                ));
        }

        $this->addColumn('type',
            array(
                'header'=> __('Type'),
                'width' => '60px',
                'index' => 'type_id',
                'type'  => 'options',
                'options' => $this->catalogProductType->getOptionArray(),
            ));

        $sets = $this->eavResourceModelEntityAttributeSetCollectionFactory->create()
            ->setEntityTypeFilter($this->catalogProductFactory->create()->getResource()->getTypeId())
            ->load()
            ->toOptionHash();

        $this->addColumn('set_name',
            array(
                'header'=> __('Attrib. Set Name'),
                'width' => '100px',
                'index' => 'attribute_set_id',
                'type'  => 'options',
                'options' => $sets,
            ));

        $this->addColumn('sku',
            array(
                'header'=> __('SKU'),
                'width' => '80px',
                'index' => 'sku',
            ));

        $this->addColumn('yc_most_recent_expiration_date',
            array(
                'header'=> __('exp Date'), //todo find the right helper
                'width' => '80px',
                'index' => 'yc_most_recent_expiration_date',
            ));

        $store = $this->_getStore();
        $this->addColumn('price',
            array(
                'header'=> __('Price'),
                'type'  => 'price',
                'currency_code' => $store->getBaseCurrency()->getCode(),
                'index' => 'price',
            ));

        if ($this->catalogHelper->isModuleEnabled('Mage_CatalogInventory')) {
            $this->addColumn('qty',
                array(
                    'header'=> __('Qty'),
                    'width' => '100px',
                    'type'  => 'number',
                    'index' => 'qty',
                ));
        }

        $this->addColumn('visibility',
            array(
                'header'=> __('Visibility'),
                'width' => '70px',
                'index' => 'visibility',
                'type'  => 'options',
                'options' => $this->catalogProductVisibilityFactory->create()->getOptionArray(),
            ));

        $this->addColumn('status',
            array(
                'header'=> __('Status2222'),
                'width' => '70px',
                'index' => 'status',
                'type'  => 'options',
                'options' => $this->catalogProductAttributeSourceStatus->getOptionArray(),
            ));



        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('websites',
                array(
                    'header'=> __('Websites'),
                    'width' => '100px',
                    'sortable'  => false,
                    'index'     => 'websites',
                    'type'      => 'options',
                    'options'   => Mage::getModel('core/website')->toOptionHash(),
                ));
        }

        $this->addColumn('action',
            array(
                'header'    => __('Action'),
                'width'     => '50px',
                'type'      => 'action',
                'getter'     => 'getId',
                'actions'   => array(
                    array(
                        'caption' => __('Edit'),
                        'url'     => array(
                            'base'=>'*/*/edit',
                            'params'=>array('store'=>$this->getRequest()->getParam('store'))
                        ),
                        'field'   => 'id'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'index'     => 'stores',
            ));

        if ($this->catalogHelper->isModuleEnabled('Mage_Rss')) {
            $this->addRssList('rss/catalog/notifystock', __('Notify Low Stock RSS'));
        }

        return parent::_prepareColumns();
    }
}
