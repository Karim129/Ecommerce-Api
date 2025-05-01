<?php

namespace Tests\Unit\Repositories;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Repositories\CartRepository;
use App\Repositories\OrderRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private OrderRepository $orderRepository;

    private CartRepository $cartRepository;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = app(OrderRepository::class);
        $this->cartRepository = app(CartRepository::class);
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create([
            'price' => 100,
            'quantity' => 10,
        ]);
    }

    public function test_can_create_order_from_cart(): void
    {
        // Add items to cart
        $this->cartRepository->addToCart($this->user, $this->product, 2);

        $orderData = [
            'city' => 'Test City',
            'address' => 'Test Address',
            'building_number' => '123',
            'payment_method' => 'cash',
        ];

        $order = $this->orderRepository->createOrder($this->user, $orderData);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertEquals($this->user->id, $order->user_id);
        $this->assertEquals(200, $order->total);
        $this->assertEquals('pending', $order->status);
        $this->assertCount(1, $order->items);

        // Check if cart was cleared
        $this->assertEmpty($this->cartRepository->getUserCart($this->user));

        // Check if product stock was updated
        $this->assertEquals(8, $this->product->fresh()->quantity);
    }

    public function test_cannot_create_order_with_empty_cart(): void
    {
        $orderData = [
            'city' => 'Test City',
            'address' => 'Test Address',
            'building_number' => '123',
            'payment_method' => 'cash',
        ];

        $order = $this->orderRepository->createOrder($this->user, $orderData);

        $this->assertNull($order);
    }

    public function test_can_get_user_orders(): void
    {
        // Add items to cart and create order
        $this->cartRepository->addToCart($this->user, $this->product, 2);

        $orderData = [
            'city' => 'Test City',
            'address' => 'Test Address',
            'building_number' => '123',
            'payment_method' => 'cash',
        ];

        $this->orderRepository->createOrder($this->user, $orderData);

        $orders = $this->orderRepository->getUserOrders($this->user);

        $this->assertEquals(1, $orders->count());
        $this->assertEquals($this->user->id, $orders->first()->user_id);
    }

    public function test_can_update_order_status(): void
    {
        // Add items to cart and create order
        $this->cartRepository->addToCart($this->user, $this->product, 2);

        $orderData = [
            'city' => 'Test City',
            'address' => 'Test Address',
            'building_number' => '123',
            'payment_method' => 'cash',
        ];

        $order = $this->orderRepository->createOrder($this->user, $orderData);

        $updated = $this->orderRepository->updateOrderStatus($order, 'processing');

        $this->assertTrue($updated);
        $this->assertEquals('processing', $order->fresh()->status);
    }
}
