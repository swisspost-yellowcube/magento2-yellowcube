<?php

use Magento\Framework\App\Config\Storage\Writer;

$objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();

/** @var \Magento\Framework\App\Config\Storage\Writer $writer */
$writer = $objectManager->get(Writer::class);

$config = [
    'active' => 1,
    'custom_url' => 1,
    'soap_url' => 'http://yellow-cube-dummy.example',
    'sender_id' => '12345',
    'depositor_number' => '54321',
    'plant_id' => 'Y022',
];

foreach ($config as $key => $value) {
    $writer->save(
        'carriers/yellowcube/' . $key,
        $value
    );
}
