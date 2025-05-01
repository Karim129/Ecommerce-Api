<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use PayPal\Api\Amount;
use PayPal\Api\Details;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\RefundRequest;
use PayPal\Api\Sale;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Stripe\StripeClient;

class PaymentService
{
    protected $stripe;

    protected $paypalContext;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('services.stripe.secret'));

        // Set up PayPal context
        $this->paypalContext = new ApiContext(
            new OAuthTokenCredential(
                config('services.paypal.client_id'),
                config('services.paypal.secret')
            )
        );

        // Set PayPal mode and configuration
        $this->paypalContext->setConfig([
            'mode' => config('services.paypal.mode', 'sandbox'),
            'log.LogEnabled' => true,
            'log.FileName' => storage_path('logs/paypal.log'),
            'log.LogLevel' => 'DEBUG',
            'cache.enabled' => false,
            'http.CURLOPT_CONNECTTIMEOUT' => 30,
        ]);
    }

    public function createStripePayment(Order $order)
    {
        try {
            if ($order->payment_status === 'paid') {
                throw new \Exception('Order is already paid');
            }

            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => (int) ($order->total_amount * 100), // Convert to cents
                'currency' => 'usd',
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
                'description' => 'Order #'.$order->order_number,
            ]);

            // Update order with payment intent ID
            $order->update(['stripe_payment_intent_id' => $paymentIntent->id]);

            return $paymentIntent;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe payment creation failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);
            throw new \Exception('Failed to create Stripe payment: '.$e->getMessage());
        }
    }

    public function refundStripePayment(Order $order)
    {
        try {
            if ($order->payment_status !== 'paid') {
                throw new \Exception('Order is not paid');
            }

            if (! $order->stripe_payment_intent_id) {
                throw new \Exception('No Stripe payment intent ID found');
            }

            // Get payment intent to find charge ID
            $paymentIntent = $this->stripe->paymentIntents->retrieve($order->stripe_payment_intent_id);

            if (! $paymentIntent->latest_charge) {
                throw new \Exception('No charge found for payment intent');
            }

            $refund = $this->stripe->refunds->create([
                'charge' => $paymentIntent->latest_charge,
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ],
            ]);

            // Update order status
            $order->update([
                'payment_status' => 'refunded',
                'refund_id' => $refund->id,
            ]);

            return $refund;
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error('Stripe refund failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);
            throw new \Exception('Failed to process refund: '.$e->getMessage());
        }
    }

    public function createPayPalPayment(Order $order)
    {
        try {
            if ($order->payment_status === 'paid') {
                throw new \Exception('Order is already paid');
            }

            $payer = new Payer;
            $payer->setPaymentMethod('paypal');

            $itemList = new ItemList;
            $items = [];
            $total = 0;

            foreach ($order->items as $orderItem) {
                $product = $orderItem->product;

                // Get current locale and fallback translations
                $currentLocale = app()->getLocale();
                $name = $product->getTranslatedAttribute('name', $currentLocale) ??
                    $product->getTranslatedAttribute('name', 'en') ??
                    'Product #'.$product->id;

                // Validate price and quantity
                if ($orderItem->quantity <= 0) {
                    throw new \Exception('Invalid quantity for product: '.$name);
                }

                if ($orderItem->price <= 0) {
                    throw new \Exception('Invalid price for product: '.$name);
                }

                // Create PayPal item with proper price formatting
                $item = new Item;
                $itemPrice = number_format($orderItem->price, 2, '.', '');
                $item->setName(mb_substr($name, 0, 127))
                    ->setCurrency('USD')
                    ->setQuantity($orderItem->quantity)
                    ->setPrice($itemPrice)
                    ->setSku((string) $product->id);

                $items[] = $item;
                $total += $orderItem->quantity * (float) $itemPrice;
            }

            if (empty($items)) {
                throw new \Exception('No items in order');
            }

            $itemList->setItems($items);

            // Calculate totals precisely
            $total = number_format($total, 2, '.', '');

            // Validate total amount matches order total
            $orderTotal = number_format($order->total_amount, 2, '.', '');
            if ($total !== $orderTotal) {
                Log::error('Order total mismatch', [
                    'calculated_total' => $total,
                    'order_total' => $orderTotal,
                    'order_id' => $order->id,
                ]);
                throw new \Exception('Order total mismatch');
            }

            // Set transaction details
            $details = new Details;
            $details->setSubtotal($total);

            // Set amount
            $amount = new Amount;
            $amount->setCurrency('USD')
                ->setTotal($total)
                ->setDetails($details);

            // Set transaction
            $transaction = new Transaction;
            $transaction->setAmount($amount)
                ->setItemList($itemList)
                ->setDescription('Order #'.$order->order_number)
                ->setInvoiceNumber(uniqid('INV-'))
                ->setCustom((string) $order->id);

            // Set redirect URLs
            $redirectUrls = new RedirectUrls;
            $redirectUrls->setReturnUrl(route('payment.paypal.success'))
                ->setCancelUrl(route('payment.paypal.cancel'));

            // Create payment
            $payment = new Payment;
            $payment->setIntent('sale')
                ->setPayer($payer)
                ->setRedirectUrls($redirectUrls)
                ->setTransactions([$transaction]);

            Log::info('Creating PayPal payment', [
                'order_id' => $order->id,
                'amount' => $total,
                'items_count' => count($items),
            ]);

            $createdPayment = $payment->create($this->paypalContext);

            Log::info('PayPal payment created', [
                'payment_id' => $createdPayment->getId(),
                'state' => $createdPayment->getState(),
                'order_id' => $order->id,
            ]);

            return $createdPayment;
        } catch (\Exception $e) {
            Log::error('PayPal payment creation failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Failed to create PayPal payment: '.$e->getMessage());
        }
    }

    public function refundPayPalPayment(Order $order, $saleId)
    {
        try {
            if ($order->payment_status !== 'paid') {
                throw new \Exception('Order is not paid');
            }

            $sale = Sale::get($saleId, $this->paypalContext);

            $refundRequest = new RefundRequest;
            $amount = new Amount;
            $amount->setCurrency('USD')
                ->setTotal(number_format($order->total_amount, 2, '.', ''));

            $refundRequest->setAmount($amount);

            $refundedSale = $sale->refundSale($refundRequest, $this->paypalContext);

            if ($refundedSale->getState() === 'completed') {
                $order->update([
                    'payment_status' => 'refunded',
                    'refund_id' => $refundedSale->getId(),
                ]);
            }

            return $refundedSale;
        } catch (\Exception $e) {
            Log::error('PayPal refund failed', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
                'sale_id' => $saleId,
            ]);
            throw new \Exception('Failed to process PayPal refund: '.$e->getMessage());
        }
    }

    public function capturePayPalPayment($paymentId, $payerId)
    {
        try {
            Log::info('Retrieving PayPal payment', [
                'payment_id' => $paymentId,
                'payer_id' => $payerId,
            ]);

            $payment = Payment::get($paymentId, $this->paypalContext);

            // Verify payment hasn't already been executed
            if ($payment->getState() === 'approved') {
                throw new \Exception('Payment has already been approved');
            }

            $execution = new PaymentExecution;
            $execution->setPayerId($payerId);

            Log::info('Executing PayPal payment', [
                'payment_id' => $paymentId,
                'payer_id' => $payerId,
                'payment_state' => $payment->getState(),
            ]);

            $result = $payment->execute($execution, $this->paypalContext);

            Log::info('PayPal payment captured', [
                'payment_id' => $paymentId,
                'payer_id' => $payerId,
                'state' => $result->getState(),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('PayPal payment capture failed', [
                'error' => $e->getMessage(),
                'payment_id' => $paymentId,
                'payer_id' => $payerId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception('Failed to capture PayPal payment: '.$e->getMessage());
        }
    }

    public function getPaymentStatus(Order $order)
    {
        try {
            if ($order->payment_method === 'stripe' && $order->stripe_payment_intent_id) {
                $paymentIntent = $this->stripe->paymentIntents->retrieve($order->stripe_payment_intent_id);

                return [
                    'provider' => 'stripe',
                    'status' => $paymentIntent->status,
                    'amount' => $paymentIntent->amount / 100,
                    'currency' => $paymentIntent->currency,
                    'payment_method' => $paymentIntent->payment_method_types[0] ?? null,
                ];
            } elseif ($order->payment_method === 'paypal' && $order->paypal_payment_id) {
                $payment = Payment::get($order->paypal_payment_id, $this->paypalContext);

                return [
                    'provider' => 'paypal',
                    'status' => $payment->getState(),
                    'amount' => $payment->getTransactions()[0]->getAmount()->getTotal(),
                    'currency' => $payment->getTransactions()[0]->getAmount()->getCurrency(),
                    'payer_email' => $payment->getPayer()->getPayerInfo()->getEmail(),
                ];
            }

            throw new \Exception('Payment information not found');
        } catch (\Exception $e) {
            Log::error('Failed to get payment status', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);
            throw new \Exception('Failed to get payment status: '.$e->getMessage());
        }
    }
}
