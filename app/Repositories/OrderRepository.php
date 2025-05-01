<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderRepository extends BaseRepository
{
    protected CartRepository $cartRepository;

    protected ProductRepository $productRepository;

    public function __construct(
        Order $model,
        CartRepository $cartRepository,
        ProductRepository $productRepository
    ) {
        parent::__construct($model);
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
    }

    public function getUserOrders(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->with(['items.product', 'user'])
            ->where('user_id', $user->id)
            ->latest()
            ->paginate($perPage);
    }

    public function getOrderDetails(int $orderId, User $user): ?Order
    {
        return $this->model
            ->with(['items.product', 'user'])
            ->where('id', $orderId)
            ->where('user_id', $user->id)
            ->firstOrFail();
    }

    public function createOrder(User $user, array $data): Order
    {
        $cartItems = $this->cartRepository->getUserCart($user->id);

        if ($cartItems->isEmpty()) {
            throw new \Exception(__('api.orders.empty_cart'));
        }

        return DB::transaction(function () use ($user, $data, $cartItems) {
            // Validate stock for all items before proceeding
            foreach ($cartItems as $cartItem) {
                if ($cartItem->product->quantity < $cartItem->quantity) {
                    throw new \Exception(__('api.orders.out_of_stock', ['name' => $cartItem->product->name]));
                }
            }

            $total = $this->cartRepository->getCartTotal($cartItems);

            $order = $this->model->create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'status' => 'pending',
                'payment_status' => $data['payment_method'] === 'cod' ? 'pending' : 'awaiting_payment',
                'payment_method' => $data['payment_method'],
                'delivery_address' => [
                    'city' => $data['delivery_address']['city'],
                    'address' => $data['delivery_address']['address'],
                    'building_number' => $data['delivery_address']['building_number'],
                ],
                'total_amount' => $total,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($cartItems as $cartItem) {
                $product = $cartItem->product;
                $price = $product->discounted_price ?? $product->price;

                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $cartItem->quantity,
                    'price' => $price,
                ]);

                $this->productRepository->updateStock($product, $cartItem->quantity);
            }

            $this->cartRepository->clearUserCart($user->id);

            return $order->load(['items.product', 'user']);
        });
    }

    public function updateOrderStatus(Order $order, array $data): Order
    {
        return DB::transaction(function () use ($order, $data) {
            $order->update([
                'status' => $data['status'],
                'payment_status' => $data['payment_status'],
            ]);

            return $order->fresh(['items.product', 'user']);
        });
    }

    protected function generateOrderNumber(): string
    {
        do {
            $number = 'ORD-'.date('Ymd').'-'.strtoupper(Str::random(6));
        } while ($this->model->where('order_number', $number)->exists());

        return $number;
    }

    public function getAdminOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->with(['items.product', 'user']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        if (isset($filters['payment_method'])) {
            $query->where('payment_method', $filters['payment_method']);
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->latest()->paginate($perPage);
    }
}
