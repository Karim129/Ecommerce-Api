<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PayPal\Api\VerifyWebhookSignature;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;

/**
 * @OA\Tag(
 *     name="Webhooks",
 *     description="Payment gateway webhook endpoints"
 * )
 */
class PayPalWebhookController extends Controller
{
    protected $apiContext;

    public function __construct()
    {
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential(
                config('services.paypal.client_id'),
                config('services.paypal.secret')
            )
        );

        $this->apiContext->setConfig([
            'mode' => config('services.paypal.mode', 'sandbox'),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/webhooks/paypal",
     *     summary="Handle PayPal webhook events",
     *     description="Handles various PayPal webhook events including payment completions, denials, and refunds",
     *     tags={"Webhooks"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         description="PayPal webhook event payload",
     *
     *         @OA\JsonContent(
     *             required={"id", "event_type", "resource"},
     *
     *             @OA\Property(property="id", type="string", example="WH-2WR32451HC0233532-67976317FL4543714"),
     *             @OA\Property(property="event_type", type="string", example="PAYMENT.CAPTURE.COMPLETED"),
     *             @OA\Property(
     *                 property="resource",
     *                 type="object",
     *                 @OA\Property(property="id", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(
     *                     property="custom_id",
     *                     type="string",
     *                     description="Contains the order ID"
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
            // Verify webhook signature
            $signatureVerification = new VerifyWebhookSignature;
            $signatureVerification->setAuthAlgo($request->header('PAYPAL-AUTH-ALGO'));
            $signatureVerification->setTransmissionId($request->header('PAYPAL-TRANSMISSION-ID'));
            $signatureVerification->setCertUrl($request->header('PAYPAL-CERT-URL'));
            $signatureVerification->setWebhookId(config('services.paypal.webhook_id'));
            $signatureVerification->setTransmissionSig($request->header('PAYPAL-TRANSMISSION-SIG'));
            $signatureVerification->setTransmissionTime($request->header('PAYPAL-TRANSMISSION-TIME'));
            $signatureVerification->setRequestBody($request->getContent());

            $verify = $signatureVerification->post($this->apiContext);

            if ($verify->getVerificationStatus() !== 'SUCCESS') {
                Log::error('PayPal webhook signature verification failed');

                return response()->json(['error' => 'Invalid signature'], 400);
            }

            $payload = $request->json()->all();
            $eventType = $payload['event_type'];

            switch ($eventType) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $orderId = $payload['resource']['custom_id'];
                    $order = Order::find($orderId);
                    if ($order) {
                        $order->update([
                            'payment_status' => 'paid',
                            'paypal_payment_id' => $payload['resource']['id'],
                        ]);
                        Log::info('PayPal payment completed', ['order_id' => $orderId]);
                    }
                    break;

                case 'PAYMENT.CAPTURE.DENIED':
                    $orderId = $payload['resource']['custom_id'];
                    $order = Order::find($orderId);
                    if ($order) {
                        // Revert stock changes
                        foreach ($order->items as $item) {
                            $item->product->increment('quantity', $item->quantity);
                        }
                        $order->delete();
                        Log::info('Order deleted due to PayPal payment denial', ['order_id' => $orderId]);
                    }
                    break;

                case 'PAYMENT.CAPTURE.REFUNDED':
                    $orderId = $payload['resource']['custom_id'];
                    $order = Order::find($orderId);
                    if ($order) {
                        $order->update([
                            'payment_status' => 'refunded',
                            'refund_id' => $payload['resource']['id'],
                        ]);
                        Log::info('PayPal refund processed', [
                            'order_id' => $orderId,
                            'refund_id' => $payload['resource']['id'],
                        ]);
                    }
                    break;

                default:
                    Log::info('Unhandled PayPal webhook event', ['event_type' => $eventType]);
                    break;
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('PayPal webhook error', ['error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
