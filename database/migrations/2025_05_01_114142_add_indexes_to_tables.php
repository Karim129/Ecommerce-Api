<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add indexes to users table
        Schema::table('users', function (Blueprint $table) {
            // email might already be indexed due to unique constraint
            if (! $this->hasIndex('users', 'users_email_index')) {
                $table->index('email');
            }
            $table->index('name');
        });

        // Add indexes to categories table
        Schema::table('categories', function (Blueprint $table) {
            $table->index('status');
        });

        // Add indexes to products table
        Schema::table('products', function (Blueprint $table) {

            $table->index('status');
            $table->index('price');
        });

        // Add indexes to orders table
        Schema::table('orders', function (Blueprint $table) {
            // user_id might already be indexed due to foreign key
            if (! $this->hasIndex('orders', 'orders_user_id_index')) {
                $table->index('user_id');
            }
            $table->index('status');
            $table->index('payment_status');
            $table->index('created_at');
        });

        // Add indexes to order_items table
        Schema::table('order_items', function (Blueprint $table) {
            if (! $this->hasIndex('order_items', 'order_items_order_id_product_id_index')) {
                $table->index(['order_id', 'product_id']);
            }
        });

        // Add indexes to carts table
        Schema::table('carts', function (Blueprint $table) {
            if (! $this->hasIndex('carts', 'carts_user_id_product_id_index')) {
                $table->index(['user_id', 'product_id']);
            }
        });
    }

    public function down(): void
    {
        // Remove indexes from users table
        Schema::table('users', function (Blueprint $table) {
            if ($this->hasIndex('users', 'users_name_index')) {
                $table->dropIndex(['name']);
            }
        });

        // Remove indexes from categories table
        Schema::table('categories', function (Blueprint $table) {
            if ($this->hasIndex('categories', 'categories_status_index')) {
                $table->dropIndex(['status']);
            }
        });

        // Remove indexes from products table
        Schema::table('products', function (Blueprint $table) {
            if ($this->hasIndex('products', 'products_status_index')) {
                $table->dropIndex(['status']);
            }
            if ($this->hasIndex('products', 'products_price_index')) {
                $table->dropIndex(['price']);
            }
        });

        // Remove indexes from orders table
        Schema::table('orders', function (Blueprint $table) {
            if ($this->hasIndex('orders', 'orders_status_index')) {
                $table->dropIndex(['status']);
            }
            if ($this->hasIndex('orders', 'orders_payment_status_index')) {
                $table->dropIndex(['payment_status']);
            }
            if ($this->hasIndex('orders', 'orders_created_at_index')) {
                $table->dropIndex(['created_at']);
            }
        });
    }

    private function hasIndex($table, $index)
    {
        try {
            $conn = Schema::getConnection();
            $dbSchemaManager = $conn->getDoctrineSchemaManager();
            $doctrineTable = $dbSchemaManager->listTableDetails($table);

            return $doctrineTable->hasIndex($index);
        } catch (\Exception $e) {
            return false;
        }
    }
};
