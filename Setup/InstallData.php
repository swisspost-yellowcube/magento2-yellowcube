<?php

namespace Swisspost\YellowCube\Setup;

use Magento\Catalog\Model\Product\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\InstallDataInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Eav\Model\Config;
use Swisspost\YellowCube\Model\Ean\Type\Source;

class InstallData implements InstallDataInterface
{

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    public function __construct(EavSetupFactory $eavSetupFactory, Config $eavConfig)
    {
        $this->eavSetupFactory = $eavSetupFactory;
        $this->eavConfig = $eavConfig;
    }

    public function install(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /** @var \Magento\Eav\Setup\EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $ycGroupName = 'Yellow Cube';

        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            'yc_sync_with_yellowcube',
            [
                'group' => $ycGroupName,
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Sync With YellowCube',
                'input' => 'select',
                'class' => '',
                'source' => Boolean::class,
                'global' => \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => true,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => true,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => 'simple,configurable,virtual',
                'default' => Boolean::VALUE_NO
            ]
        );

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
                'used_in_product_listing' => true,
                'unique' => false,
                'apply_to' => 'simple,configurable,virtual',
            ]
        );

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
                'source' => Source::class,
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
                'unique' => false,
                'apply_to' => 'simple,configurable,virtual',
            ]
        );

    }
}
