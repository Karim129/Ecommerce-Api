<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Stripe\Event;
use Stripe\Webhook;

/**
 * @OA\Tag(
 *     name="Webhooks",
 *     description="Payment gateway webhook endpoints"
 * )
 */
class StripeWebhookController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/webhooks/stripe",
     *     summary="Handle Stripe webhook events",
     *     description="Handles various Stripe webhook events including payment successes, failures, and refunds",
     *     tags={"Webhooks"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="Stripe webhook event payload",
     *
     *         @OA\JsonContent(
     *             required={"id", "type", "data"},
     *
     *             @OA\Property(property="id", type="string", example="evt_1234567890"),
     *             @OA\Property(property="type", type="string", example="payment_intent.succeeded"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="object",
     *                     type="object",
     *                     @OA\Property(property="id", type="string"),
     *                     @OA\Property(property="status", type="string"),
     *                     @OA\Property(
     *                         property="metadata",
     *                         type="object",
     *                         @OA\Property(property="order_id", type="string")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Webhook processed successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid payload or signature",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string")
     *         )
     *     )
     * )
     */
    public function handleWebhook(Request $request)
    {
        try {
            $signature = $request->header('Stripe-Signature');
            $event = Webhook::constructEvent(
                $request->getContent(),
                $signature,
                config('services.stripe.webhook_secret')
            );

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event->data->object;
                    $orderId = $paymentIntent->metadata->order_id;

                    $order = Order::find($orderId);
                    if ($order) {
                        $order->update([
                            'payment_status' => 'paid',
                        ]);
                        \Log::info('Order marked as paid', ['order_id' => $orderId]);
                    }
                    break;

                case 'payment_intent.payment_failed':
                    $paymentIntent = $event->data->object;
                    $orderId = $paymentIntent->metadata->order_id;

                    $order = Order::find($orderId);
                    if ($order) {
                        // Revert stock changes
                        foreach ($order->items as $item) {
                            $item->product->increment('quantity', $item->quantity);
                        }
                        $order->delete();
                        \Log::info('Order deleted due to payment failure', ['order_id' => $orderId]);
                    }
                    break;

                case 'charge.refunded':
                    $charge = $event->data->object;
                    $paymentIntentId = $charge->payment_intent;

                    $order = Order::where('stripe_payment_intent_id', $paymentIntentId)->first();
                    if ($order) {
                        $order->update([
                            'payment_status' => 'refunded',
                            'refund_id' => $charge->refunds->data[0]->id,
                        ]);
                        \Log::info('Order marked as refunded', [
                            'order_id' => $order->id,
                            'refund_id' => $charge->refunds->data[0]->id,
                        ]);
                    }
                    break;
            }

            return response()->json(['status' => 'success']);
        } catch (\UnexpectedValueException $e) {
            \Log::error('Invalid payload', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            \Log::error('Invalid signature', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }
    }
}
