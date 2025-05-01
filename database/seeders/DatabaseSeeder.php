<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call(AdminSeeder::class);

        // Users
        $this->call(UserSeeder::class);

        // Categories
        $this->call(CategorySeeder::class);
        // Products (each product belongs to 1-2 categories)
        $this->call(ProductSeeder::class);

        // Orders
        $this->call(OrderSeeder::class);

        // Order Items (each order has 1-3 products)
        $this->call(OrderItemSeeder::class);

        // Carts
        $this->call(CartSeeder::class);
    }
}
