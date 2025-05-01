<?php

namespace Tests\Unit\Repositories;

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use App\Repositories\CartRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private CartRepository $cartRepository;

    private User $user;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cartRepository = app(CartRepository::class);
        $this->user = User::factory()->create();
        $this->product = Product::factory()->create([
            'price' => 100,
            'quantity' => 10,
        ]);
    }

    public function test_can_add_item_to_cart(): void
    {
        $cartItem = $this->cartRepository->addToCart($this->user, $this->product, 2);

        $this->assertInstanceOf(Cart::class, $cartItem);
        $this->assertEquals($this->user->id, $cartItem->user_id);
        $this->assertEquals($this->product->id, $cartItem->product_id);
        $this->assertEquals(2, $cartItem->quantity);
    }

    public function test_can_update_quantity_in_cart(): void
    {
        $cartItem = $this->cartRepository->addToCart($this->user, $this->product, 2);
        $updated = $this->cartRepository->updateQuantity($cartItem, 3);

        $this->assertTrue($updated);
        $this->assertEquals(3, $cartItem->fresh()->quantity);
    }

    public function test_can_remove_item_from_cart(): void
    {
        $cartItem = $this->cartRepository->addToCart($this->user, $this->product, 2);
        $deleted = $this->cartRepository->removeFromCart($cartItem);

        $this->assertTrue($deleted);
        $this->assertDatabaseMissing('carts', ['id' => $cartItem->id]);
    }

    public function test_can_clear_user_cart(): void
    {
        $this->cartRepository->addToCart($this->user, $this->product, 2);
        $cleared = $this->cartRepository->clearCart($this->user);

        $this->assertTrue($cleared);
        $this->assertDatabaseMissing('carts', ['user_id' => $this->user->id]);
    }

    public function test_can_get_cart_total(): void
    {
        $this->cartRepository->addToCart($this->user, $this->product, 2);
        $total = $this->cartRepository->getTotal($this->user);

        $this->assertEquals(200, $total); // 2 items * $100 each
    }

    public function test_can_get_user_cart(): void
    {
        $this->cartRepository->addToCart($this->user, $this->product, 2);
        $cartItems = $this->cartRepository->getUserCart($this->user);

        $this->assertCount(1, $cartItems);
        $this->assertEquals($this->product->id, $cartItems->first()->product_id);
    }
}
