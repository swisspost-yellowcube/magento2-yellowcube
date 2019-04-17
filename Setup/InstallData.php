<?php

namespace Swisspost\YellowCube\Setup;

use Magento\Catalog\Model\Product\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Model\Config;
use Magento\Inventory\Model\SourceRepository;
use Magento\InventoryApi\Api\Data\SourceInterfaceFactory;
use Swisspost\YellowCube\Model\Ean\Type\Source;

class InstallData implements InstallDataInterface
{

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @var SourceRepository
     */
    private $sourceRepository;

    /**
     * @var SourceInterfaceFactory
     */
    private $sourceFactory;

    public function __construct(EavSetupFactory $eavSetupFactory, Config $eavConfig, SourceRepository $sourceRepository, SourceInterfaceFactory $sourceFactory)
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
        $this->sourceRepository = $sourceRepository;
        $this->sourceFactory = $sourceFactory;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /** @var \Magento\Eav\Setup\EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $ycGroupName = 'Yellow Cube';

        //$eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'yc_sync_with_yellowcube');
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'yc_sync_with_yellowcube',
            [
                'group' => $ycGroupName,
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Sync With YellowCube',
                'input' => 'boolean',
                'class' => '',
                'source' => Boolean::class,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'is_used_in_grid' => true,
                'is_filterable_in_grid' => true,
                'unique' => false,
                'apply_to' => 'simple',
                'default' => Boolean::VALUE_NO
            ]
        );

        //$eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'yc_ean_type');
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'yc_ean_type',
            [
                'group' => $ycGroupName,
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'EAN Type',
                'input' => 'select',
                'class' => '',
                'source' => Source::class,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => 'simple',
            ]
        );

        //$eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'yc_ean_code');
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'yc_ean_code',
            [
                'group' => $ycGroupName,
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'EAN Code',
                'input' => 'text',
                'class' => '',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => true,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'is_used_in_grid' => true,
                'is_filterable_in_grid' => true,
                'unique' => false,
                'apply_to' => 'simple',
            ]
        );

        //$eavSetup->removeAttribute(\Magento\Catalog\Model\Product::ENTITY, 'yc_stock');
        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'yc_stock',
            [
                'group' => $ycGroupName,
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'YellowCube Stock',
                'input' => 'text',
                'class' => '',
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => false,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'is_used_in_grid' => true,
                'is_filterable_in_grid' => true,
                'unique' => false,
                'apply_to' => 'simple',
            ]
        );

        try {
            $this->sourceRepository->get('YellowCube');
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $source = $this->sourceFactory->create();
            $source->setName('YellowCube');
            $source->setSourceCode('YellowCube');
            $source->setCountryId('CH');
            $source->setPostcode('4665');
            $this->sourceRepository->save($source);
        }

    }
}
