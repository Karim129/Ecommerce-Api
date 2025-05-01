<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run()
    {
        $users = User::all();
        foreach ($users as $user) {
            // Each user gets 1-3 orders
            Order::factory(rand(1, 3))->create([
                'user_id' => $user->id,
            ]);
        }
    }
}
