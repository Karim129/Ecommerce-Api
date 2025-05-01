<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        // Generate fake Arabic text for categories
        $arCategories = [
            'إلكترونيات' => 'أحدث الأجهزة الإلكترونية والتقنيات المبتكرة',
            'ملابس' => 'تشكيلة واسعة من الملابس العصرية',
            'أثاث' => 'أثاث منزلي بتصاميم عصرية وجودة عالية',
            'مطبخ' => 'مستلزمات المطبخ والأدوات المنزلية',
            'رياضة' => 'معدات رياضية احترافية للتمارين المنزلية',
            'كتب' => 'مجموعة متنوعة من الكتب والمراجع',
            'هدايا' => 'هدايا مميزة لجميع المناسبات',
            'العاب' => 'ألعاب تعليمية وترفيهية للأطفال',
            'حدائق' => 'مستلزمات الحدائق والزراعة المنزلية',
            'سيارات' => 'اكسسوارات وقطع غيار السيارات',
        ];

        $arCat = array_rand($arCategories);

        return [
            'name' => [
                'en' => ucfirst($this->faker->word()),
                'ar' => $arCat,
            ],
            'description' => [
                'en' => $this->faker->sentence(8),
                'ar' => $arCategories[$arCat],
            ],
            'image' => 'categories/cat_'.uniqid().'.jpg',
            'status' => $this->faker->randomElement(['active', 'not active']),
        ];
    }
}
