<?php

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

/** @var $product \Magento\Catalog\Model\Product */
$product = $objectManager->create('Magento\Catalog\Model\Product');
$product
    ->setId(1)
    ->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
    ->setAttributeSetId(4)
    ->setWebsiteIds([1])
    ->setName('Simple Product 1 with a very long title that needs to be shortened')
    ->setSku('simple1')
    ->setPrice(10)
    ->setData('is_salable', 1)
    ->setWeight(1)
    ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
    ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
    ->setData('ts_dimensions_length', 15)
    ->setData('ts_dimensions_width', 10)
    ->setData('ts_dimensions_height', 20)
    ->save();

/** @var $product \Magento\Catalog\Model\Product */
$product = $objectManager->create('Magento\Catalog\Model\Product');
$product
    ->setId(2)
    ->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE)
    ->setAttributeSetId(4)
    ->setWebsiteIds([1])
    ->setName('Simple Product 2')
    ->setSku('simple2')
    ->setPrice(15)
    ->setWeight(2)
    ->setData('is_salable', 1)
    ->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH)
    ->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
    ->setData('ts_dimensions_length', 7)
    ->setData('ts_dimensions_width', 3)
    ->setData('ts_dimensions_height', 9)
    ->save();
