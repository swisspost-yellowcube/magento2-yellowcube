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
    'allowed_methods' => json_encode([
        '_1554908033782_782' => [
                'allowed_methods' => 'ECO',
                'price' => '5',
            ],
        '_1554908037123_123' => [
                'allowed_methods' => 'PRI SI',
                'price' => '10',
            ],
    ]),
];

foreach ($config as $key => $value) {
    $writer->save(
        'carriers/yellowcube/' . $key,
        $value
    );
}
