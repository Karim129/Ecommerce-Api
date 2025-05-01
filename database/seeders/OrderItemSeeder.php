<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\Seeder;

class OrderItemSeeder extends Seeder
{
    public function run()
    {
        $orders = Order::all();
        $products = Product::all();
        foreach ($orders as $order) {
            // Each order gets 1-5 order items
            for ($i = 0; $i < rand(1, 5); $i++) {
                $product = $products->random();
                OrderItem::factory()->create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'price' => $product->price,
                ]);
            }
        }
    }
}
