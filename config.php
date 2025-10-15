<?php
return [
    'api_key' => 'YOUR_NAMESILO_API_KEY',
    'sandbox' => false,
    'test_mode' => false,
    'pricing' => [
        'com' => ['register' => 14.99, 'renew' => 15.99, 'transfer' => 14.99],
        'net' => ['register' => 15.99, 'renew' => 16.99, 'transfer' => 15.99],
        'org' => ['register' => 16.99, 'renew' => 17.99, 'transfer' => 16.99],
        'info' => ['register' => 9.99, 'renew' => 10.99, 'transfer' => 9.99],
        'biz' => ['register' => 16.99, 'renew' => 17.99, 'transfer' => 16.99]
    ],
    'default_contact' => [
        'fn' => 'First Name',
        'ln' => 'Last Name',
        'ad' => '123 Main St',
        'cy' => 'City',
        'st' => 'State',
        'zp' => '12345',
        'ct' => 'BD', // Country code
        'em' => 'email@example.com',
        'ph' => '123.123.1234'
    ]
];
?>