<?php

return [
    'accepted' => 'The :attribute field must be accepted.',
    'accepted_if' => 'The :attribute field must be accepted when :other is :value.',
    // ... existing validation messages ...

    'custom' => [
        'quantity' => [
            'exceeds_stock' => 'The requested quantity exceeds available stock.',
        ],
        'payment_token' => [
            'required_for_online' => 'Payment token is required for online payment methods.',
        ],
        'delivery_address' => [
            'required' => 'Delivery address details are required.',
        ],
        'name' => [
            'en' => [
                'required' => 'The English name is required.',
            ],
            'ar' => [
                'required' => 'The Arabic name is required.',
            ],
        ],
        'description' => [
            'en' => [
                'required' => 'The English description is required.',
            ],
            'ar' => [
                'required' => 'The Arabic description is required.',
            ],
        ],
    ],
];
