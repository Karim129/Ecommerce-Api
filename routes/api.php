<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\OrderController;
use App\Http\Controllers\API\PayPalWebhookController;
use App\Http\Controllers\API\ProductController;
use App\Http\Controllers\API\StripeWebhookController;
use App\Http\Controllers\API\UserController;
use App\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('register', [AuthController::class, 'register'])
    ->middleware(['throttle:6,1']);

Route::post('login', [AuthController::class, 'login'])
    ->middleware(['throttle:6,1', EnsureEmailIsVerified::class]);

Route::post('login/google', [AuthController::class, 'googleLogin'])->middleware(['throttle:6,1']);

// Webhook routes (must be public)
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handleWebhook']);
Route::post('webhooks/paypal', [PayPalWebhookController::class, 'handleWebhook']);

// Public Product and Category routes
Route::get('categories', [CategoryController::class, 'index']);
Route::get('categories/{category}', [CategoryController::class, 'show']);
Route::get('products', [ProductController::class, 'index']);
Route::get('products/{product}', [ProductController::class, 'show']);

// Email verification routes
Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware(['signed'])
    ->name('verification.verify');
Route::post('email/resend', [AuthController::class, 'resendVerification'])
    ->middleware(['auth:sanctum', 'throttle:6,1'])
    ->name('verification.send');

// Protected routes requiring email verification
Route::middleware(['auth:sanctum'])->group(function () {
    // Payment routes
    Route::get('payment/paypal/success', [OrderController::class, 'handlePayPalSuccess'])->name('payment.paypal.success');
    Route::get('payment/paypal/cancel', [OrderController::class, 'handlePayPalCancel'])->name('payment.paypal.cancel');

    // User
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('user', [AuthController::class, 'user']);

    // Cart
    Route::get('cart', [CartController::class, 'index']);
    Route::post('cart', [CartController::class, 'store']);
    Route::put('cart/{product}', [CartController::class, 'update']);
    Route::delete('cart/{product}', [CartController::class, 'destroy']);
    Route::post('cart/clear', [CartController::class, 'clear']);

    // Orders
    Route::get('orders', [OrderController::class, 'index']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::post('orders/{order}/refund', [OrderController::class, 'refund']);
    Route::get('orders/{order}/payment-status', [OrderController::class, 'getPaymentStatus']);

    // Admin routes
    Route::middleware(['can:admin'])->group(function () {
        // Categories management
        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        // Products management
        Route::post('products', [ProductController::class, 'store']);
        Route::put('products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);

        // Order management
        Route::put('orders/{order}/status', [OrderController::class, 'updateStatus']);
        Route::delete('orders/{order}', [OrderController::class, 'destroy']);
        Route::apiResource('users', UserController::class);
    });
});
