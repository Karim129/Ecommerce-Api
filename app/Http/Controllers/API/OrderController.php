<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\PaymentService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * @OA\Tag(
 *     name="Orders",
 *     description="API Endpoints for order management"
 * )
 *
 * @OA\Schema(
 *     schema="Order",
 *     required={"id", "order_number", "user_id", "status", "total_amount"},
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="order_number", type="string"),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="email", type="string", format="email")
 *     ),
 *     @OA\Property(property="status", type="string", enum={"pending", "shipped", "delivered"}),
 *     @OA\Property(property="payment_status", type="string", enum={"paid", "not_paid"}),
 *     @OA\Property(property="payment_method", type="string", enum={"stripe", "paypal"}),
 *     @OA\Property(property="stripe_payment_intent_id", type="string", nullable=true),
 *     @OA\Property(property="paypal_payment_id", type="string", nullable=true),
 *     @OA\Property(property="refund_id", type="string", nullable=true),
 *     @OA\Property(property="city", type="string"),
 *     @OA\Property(property="address", type="string"),
 *     @OA\Property(property="building_number", type="string"),
 *     @OA\Property(property="total_amount", type="number", format="float"),
 *     @OA\Property(property="notes", type="string", nullable=true),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *
 *         @OA\Items(ref="#/components/schemas/OrderItem")
 *     ),
 *
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="OrderItem",
 *     required={"id", "order_id", "product_id", "quantity", "price", "total"},
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="order_id", type="integer"),
 *     @OA\Property(property="product", ref="#/components/schemas/Product"),
 *     @OA\Property(property="quantity", type="integer", minimum=1),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="total", type="number", format="float"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class OrderController extends Controller
{
    use ApiResponse;

    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * @OA\Get(
     *     path="/api/orders",
     *     summary="List user's orders",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Language code (en, ar)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"})
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of orders",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Order")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function index(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with(['items.product'])
            ->latest()
            ->paginate(10);

        return OrderResource::collection($orders);
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{id}",
     *     summary="Get order details",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Language code (en, ar)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"})
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Order details",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function show(Order $order)
    {
        $this->authorize('view', $order);

        return new OrderResource($order->load(['items.product', 'user']));
    }

    /**
     * @OA\Post(
     *     path="/api/orders",
     *     summary="Create a new order",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Language code (en, ar)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"})
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"city", "address", "building_number", "payment_method"},
     *
     *             @OA\Property(property="city", type="string"),
     *             @OA\Property(property="address", type="string"),
     *             @OA\Property(property="building_number", type="string"),
     *             @OA\Property(property="payment_method", type="string", enum={"stripe", "paypal"}),
     *             @OA\Property(property="notes", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="order", ref="#/components/schemas/Order"),
     *             @OA\Property(
     *                 property="client_secret",
     *                 type="string",
     *                 description="Stripe payment intent client secret"
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or cart is empty",
     *
     *         @OA\JsonContent(ref="#/components/schemas/ValidationError")
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'city' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'building_number' => 'required|string|max:255',
            'payment_method' => 'required|in:stripe,paypal',
            'notes' => 'nullable|string|max:1000',
        ]);

        $cartItems = $request->user()->cart()->with('product')->get();
        if ($cartItems->isEmpty()) {
            return response()->json([
                'message' => __('api.orders.empty_cart'),
            ], 422);
        }

        // Calculate total and validate stock
        $total = 0;
        foreach ($cartItems as $item) {
            if ($item->quantity > $item->product->quantity) {
                return response()->json([
                    'message' => __('api.orders.out_of_stock', ['name' => $item->product->name]),
                ], 422);
            }
            $price = $item->product->discounted_price ?? $item->product->price;
            $total += $price * $item->quantity;
        }

        // Create order
        $order = Order::create([
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'user_id' => $request->user()->id,
            'status' => 'pending',
            'city' => $request->city,
            'address' => $request->address,
            'building_number' => $request->building_number,
            'payment_method' => $request->payment_method,
            'payment_status' => 'not_paid',
            'total_amount' => $total,
            'notes' => $request->notes,
        ]);

        // Create order items and update stock
        foreach ($cartItems as $item) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->product->discounted_price ?? $item->product->price,
                'total' => ($item->product->discounted_price ?? $item->product->price) * $item->quantity,
            ]);

            $item->product->decrement('quantity', $item->quantity);
        }

        // Clear cart
        $request->user()->cart()->delete();

        try {
            if ($request->payment_method === 'stripe') {
                $paymentIntent = $this->paymentService->createStripePayment($order);

                return response()->json([
                    'order' => new OrderResource($order->load(['items.product', 'user'])),
                    'client_secret' => $paymentIntent->client_secret,
                ]);
            } else {
                $payment = $this->paymentService->createPayPalPayment($order);

                // Find the approval URL from the payment links
                $approvalUrl = '';
                foreach ($payment->getLinks() as $link) {
                    if ($link->getRel() === 'approval_url') {
                        $approvalUrl = $link->getHref();
                        break;
                    }
                }

                if (! $approvalUrl) {
                    throw new \Exception('Could not get PayPal approval URL');
                }

                return response()->json([
                    'order' => new OrderResource($order->load(['items.product', 'user'])),
                    'payment_id' => $payment->getId(),
                    'approval_url' => $approvalUrl,
                ]);
            }
        } catch (\Exception $e) {
            // Revert stock changes on payment initialization failure
            foreach ($order->items as $item) {
                $item->product->increment('quantity', $item->quantity);
            }
            $order->delete();

            Log::error('Payment creation failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'payment_method' => $request->payment_method,
            ]);

            return response()->json([
                'message' => __('api.orders.payment_failed', ['message' => $e->getMessage()]),
            ], 422);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/orders/{id}/status",
     *     summary="Update order status (Admin only)",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Parameter(
     *         name="Accept-Language",
     *         in="header",
     *         description="Language code (en, ar)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"en", "ar"})
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"status"},
     *
     *             @OA\Property(property="status", type="string", enum={"pending", "shipped", "delivered"})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Order status updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/Order")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - Admin only",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     )
     * )
     */
    public function updateStatus(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        $request->validate([
            'status' => 'required|in:pending,shipped,delivered',
        ]);

        $order->update(['status' => $request->status]);

        return new OrderResource($order->load(['items.product', 'user']));
    }

    public function destroy(Order $order)
    {
        $this->authorize('delete', $order);

        $order->delete();

        return response()->json([
            'message' => __('api.orders.deleted'),
        ]);
    }

    public function handlePayPalSuccess(Request $request)
    {
        Log::info('PayPal success callback received', [
            'payment_id' => $request->paymentId,
            'payer_id' => $request->PayerID,
        ]);

        try {
            if (! $request->paymentId || ! $request->PayerID) {
                Log::error('Missing PayPal parameters', [
                    'payment_id' => $request->paymentId,
                    'payer_id' => $request->PayerID,
                ]);

                return response()->json([
                    'message' => __('api.orders.payment_failed', ['message' => 'Missing payment parameters']),
                ], 422);
            }

            $result = $this->paymentService->capturePayPalPayment(
                $request->paymentId,
                $request->PayerID
            );

            if ($result->getState() === 'approved') {
                $orderId = $result->getTransactions()[0]->getCustom();
                $order = Order::find($orderId);

                if ($order) {
                    $order->update(['payment_status' => 'paid']);
                    Log::info('PayPal payment captured successfully', [
                        'order_id' => $orderId,
                        'payment_id' => $request->paymentId,
                    ]);

                    return response()->json([
                        'message' => __('api.orders.payment_success'),
                        'order' => new OrderResource($order->load(['items.product', 'user'])),
                    ]);
                } else {
                    Log::error('Order not found after PayPal payment capture', [
                        'order_id' => $orderId,
                        'payment_id' => $request->paymentId,
                    ]);
                }
            } else {
                Log::error('PayPal payment not approved', [
                    'state' => $result->getState(),
                    'payment_id' => $request->paymentId,
                ]);
            }

            return response()->json([
                'message' => __('api.orders.payment_failed', ['message' => 'Payment was not approved']),
            ], 422);
        } catch (\Exception $e) {
            Log::error('PayPal payment capture failed', [
                'error' => $e->getMessage(),
                'payment_id' => $request->paymentId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => __('api.orders.payment_failed', ['message' => $e->getMessage()]),
            ], 422);
        }
    }

    public function handlePayPalCancel(Request $request)
    {
        Log::info('PayPal payment cancelled by user');

        $order = Order::where('payment_method', 'paypal')
            ->where('payment_status', 'awaiting_payment')
            ->latest()
            ->first();

        if ($order) {
            Log::info('Reverting order after PayPal cancellation', ['order_id' => $order->id]);

            foreach ($order->items as $item) {
                $item->product->increment('quantity', $item->quantity);
            }
            $order->delete();

            Log::info('Order deleted and stock restored after PayPal cancellation', ['order_id' => $order->id]);
        } else {
            Log::warning('No pending PayPal order found for cancellation');
        }

        return response()->json(['message' => __('api.orders.payment_cancelled')]);
    }

    /**
     * @OA\Post(
     *     path="/api/orders/{id}/refund",
     *     summary="Refund an order",
     *     description="Process a refund for an order. Requires admin privileges.",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Refund processed successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="order", ref="#/components/schemas/Order")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - Admin only",
     *
     *         @OA\JsonContent(ref="#/components/schemas/UnauthorizedError")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Order not paid or refund failed",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function refund(Order $order)
    {
        $this->authorize('refund', $order);

        if ($order->payment_status !== 'paid') {
            return response()->json([
                'message' => __('api.orders.not_paid'),
            ], 422);
        }

        try {
            if ($order->payment_method === 'stripe') {
                $refund = $this->paymentService->refundStripePayment($order);
            } else {
                // For PayPal, we need the sale ID from the payment execution
                $payment = $this->paymentService->getPayPalPayment($order->paypal_payment_id);
                $saleId = $payment->getTransactions()[0]->getRelatedResources()[0]->getSale()->getId();
                $refund = $this->paymentService->refundPayPalPayment($order, $saleId);
            }

            // Restore product stock
            foreach ($order->items as $item) {
                $item->product->increment('quantity', $item->quantity);
            }

            return response()->json([
                'message' => __('api.orders.refunded'),
                'order' => new OrderResource($order->fresh(['items.product', 'user'])),
            ]);
        } catch (\Exception $e) {
            Log::error('Refund failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => __('api.orders.refund_failed', ['message' => $e->getMessage()]),
            ], 422);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/orders/{id}/payment-status",
     *     summary="Get order payment status",
     *     description="Retrieve detailed payment status for an order",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payment status retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="provider", type="string", enum={"stripe", "paypal"}),
     *             @OA\Property(property="status", type="string"),
     *             @OA\Property(property="amount", type="number", format="float"),
     *             @OA\Property(property="currency", type="string"),
     *             @OA\Property(property="payment_method", type="string", nullable=true),
     *             @OA\Property(property="payer_email", type="string", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Order not found",
     *
     *         @OA\JsonContent(ref="#/components/schemas/NotFoundError")
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Failed to get payment status",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function getPaymentStatus(Order $order)
    {
        $this->authorize('view', $order);

        try {
            $status = $this->paymentService->getPaymentStatus($order);

            return response()->json($status);
        } catch (\Exception $e) {
            Log::error('Failed to get payment status', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            return response()->json([
                'message' => __('api.orders.payment_status_failed', ['message' => $e->getMessage()]),
            ], 422);
        }
    }
}
