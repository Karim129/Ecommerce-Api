<?php

namespace Database\Seeders;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class CartSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();
        $products = Product::all();
        foreach ($users as $user) {
            // Each user gets 1-5 cart items
            Cart::factory(rand(1, 5))->create([
                'user_id' => $user->id,
                'product_id' => $products->random()->id,
            ]);
        }
    }
}
