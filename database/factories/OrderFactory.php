<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition()
    {
        return [
            'user_id' => User::factory(),
            'order_number' => $this->faker->unique()->numerify('ORD#####'),
            'city' => $this->faker->city(),
            'address' => $this->faker->streetAddress(),
            'building_number' => $this->faker->buildingNumber(),

            'status' => $this->faker->randomElement(['pending', 'shipped', 'delivered']),
            'payment_method' => $this->faker->randomElement(['stripe', 'paypal']),
            'payment_status' => $this->faker->randomElement(['paid', 'not_paid']),
            'total_amount' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }
}
