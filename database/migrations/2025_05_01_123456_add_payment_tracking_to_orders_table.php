<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('stripe_payment_intent_id')->nullable()->after('payment_status');
            $table->string('paypal_payment_id')->nullable()->after('stripe_payment_intent_id');
            $table->string('refund_id')->nullable()->after('paypal_payment_id');
            // Update payment_status enum to include refunded status
            $table->enum('payment_status', ['paid', 'not_paid', 'refunded'])->default('not_paid')->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['stripe_payment_intent_id', 'paypal_payment_id', 'refund_id']);
            $table->enum('payment_status', ['paid', 'not_paid'])->default('not_paid')->change();
        });
    }
};
