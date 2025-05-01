<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        $price = $this->faker->randomFloat(2, 10, 1000);
        $discounted = $this->faker->boolean(30) ? $this->faker->randomFloat(2, 5, $price) : null;

        // Generate fake Arabic text for name and description
        $arWords = ['منتج', 'جهاز', 'هاتف', 'حاسوب', 'طابعة'];
        $arDescriptions = [
            'جودة عالية وسعر مناسب',
            'منتج ممتاز مع ضمان',
            'تصميم عصري وأداء قوي',
            'أفضل اختيار للمستخدمين',
            'متوفر بألوان متعددة',
        ];

        return [
            'name' => [
                'en' => ucwords($this->faker->words(3, true)),
                'ar' => $arWords[array_rand($arWords)].' '.$this->faker->numberBetween(1, 100),
            ],
            'description' => [
                'en' => $this->faker->paragraph(2),
                'ar' => $arDescriptions[array_rand($arDescriptions)],
            ],
            'images' => [
                'products/img1_'.uniqid().'.jpg',
                'products/img2_'.uniqid().'.jpg',
            ],
            'price' => $price,
            'discounted_price' => $discounted,
            'quantity' => $this->faker->numberBetween(1, 100),
            'status' => $this->faker->randomElement(['active', 'inactive']),
        ];
    }
}
