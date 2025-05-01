<?php

return [
    // ... existing validation messages ...

    'custom' => [
        'quantity' => [
            'exceeds_stock' => 'الكمية المطلوبة تتجاوز المخزون المتوفر.',
        ],
        'payment_token' => [
            'required_for_online' => 'رمز الدفع مطلوب لطرق الدفع عبر الإنترنت.',
        ],
        'delivery_address' => [
            'required' => 'تفاصيل عنوان التسليم مطلوبة.',
        ],
        'name' => [
            'en' => [
                'required' => 'الاسم باللغة الإنجليزية مطلوب.',
            ],
            'ar' => [
                'required' => 'الاسم باللغة العربية مطلوب.',
            ],
        ],
        'description' => [
            'en' => [
                'required' => 'الوصف باللغة الإنجليزية مطلوب.',
            ],
            'ar' => [
                'required' => 'الوصف باللغة العربية مطلوب.',
            ],
        ],
    ],
];
