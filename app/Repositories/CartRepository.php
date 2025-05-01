<?php

namespace App\Repositories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CartRepository extends BaseRepository
{
    public function __construct(Cart $model)
    {
        parent::__construct($model);
    }

    public function getUserCart(int $userId): Collection
    {
        return $this->model
            ->with(['product' => function ($query) {
                $query->with('categories');
            }])
            ->where('user_id', $userId)
            ->activeProducts()
            ->get();
    }

    public function addToCart(array $data): Cart
    {
        return DB::transaction(function () use ($data) {
            $existingItem = $this->model
                ->where('user_id', $data['user_id'])
                ->where('product_id', $data['product_id'])
                ->first();

            if ($existingItem) {
                $newQuantity = $existingItem->quantity + $data['quantity'];
                $this->validateStock($data['product_id'], $newQuantity);

                $existingItem->update(['quantity' => $newQuantity]);

                return $existingItem->fresh(['product.categories']);
            }

            $this->validateStock($data['product_id'], $data['quantity']);

            return $this->model->create($data)->load('product.categories');
        });
    }

    public function updateCartItem(Cart $cart, int $quantity): Cart
    {
        return DB::transaction(function () use ($cart, $quantity) {
            $this->validateStock($cart->product_id, $quantity);

            $cart->update(['quantity' => $quantity]);

            return $cart->fresh(['product.categories']);
        });
    }

    public function clearUserCart(int $userId): bool
    {
        return $this->model->where('user_id', $userId)->delete();
    }

    protected function validateStock(int $productId, int $requestedQuantity): void
    {
        $product = DB::table('products')
            ->where('id', $productId)
            ->where('status', 'active')
            ->first();

        if (! $product || $product->quantity < $requestedQuantity) {
            throw new \Exception(__('api.cart.insufficient_stock'));
        }
    }

    public function getCartTotal(Collection $cartItems): float
    {
        return $cartItems->sum(function ($item) {
            return $item->quantity * ($item->product->discounted_price ?? $item->product->price);
        });
    }
}
