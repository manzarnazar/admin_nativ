<?php

namespace App\Services\Payments\Providers;

use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Models\Setting;
use App\Services\Payments\Exceptions\PaymentGatewayException;
use App\Services\Payments\Exceptions\SignatureVerificationException;
use Illuminate\Support\Facades\Http;

// Stripe payment provider implementation.

class StripeProvider extends BasePaymentProvider
{
    protected function baseUrl(): string
    {
        return 'https://api.stripe.com/v1';
    }

    public function createPayment(Payment $payment): array
    {
        $settings = $this->requireSettings();

        $frontendBase = rtrim(Setting::get('frontend_web_url') ?: config('services.frontend.url'), '/');

        // India RBI export rules require name + address on the PaymentIntent.
        // billing_address_collection=required forces Stripe to collect the full
        // billing address on its hosted checkout page; cardholder name comes
        // from the card form. Together this satisfies the export requirement.
        $payload = [
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'billing_address_collection' => 'required',
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($payment->currency),
                    'product_data' => ['name' => 'Booking #'.$payment->booking_id],
                    'unit_amount' => (int) round($payment->amount * 100),
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'booking_id' => (string) $payment->booking_id,
                'payment_id' => (string) $payment->id,
            ],
            'success_url' => $frontendBase.'/confirm-booking?status=success&booking_id='.$payment->booking_id.'&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $frontendBase.'/confirm-booking?status=failed&booking_id='.$payment->booking_id,
        ];

        if ($email = $payment->user?->email) {
            $payload['customer_email'] = $email;
        }

        $response = Http::withToken($settings->api_secret)
            ->asForm()
            ->post($this->baseUrl().'/checkout/sessions', $payload);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Stripe session creation failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'gateway_order_id' => $data['id'],
            'payment_url' => $data['url'] ?? null,
            'payment_token' => $data['id'],
            'raw' => $data,
        ];
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $settings = $this->requireSettings();
        $signatureHeader = $headers['stripe-signature'] ?? $headers['Stripe-Signature'] ?? null;

        if (! $signatureHeader) {
            throw new SignatureVerificationException('Stripe signature header missing.');
        }

        // Parse "t=timestamp,v1=signature"
        $parts = [];
        foreach (explode(',', $signatureHeader) as $part) {
            [$k, $v] = array_pad(explode('=', $part, 2), 2, null);
            $parts[$k] = $v;
        }

        $timestamp = $parts['t'] ?? null;
        $expectedSig = $parts['v1'] ?? null;

        if (! $timestamp || ! $expectedSig) {
            throw new SignatureVerificationException('Stripe signature malformed.');
        }

        $signedPayload = $timestamp.'.'.$payload;
        $computed = hash_hmac('sha256', $signedPayload, $settings->webhook_secret);

        if (! hash_equals($computed, $expectedSig)) {
            throw new SignatureVerificationException('Stripe signature mismatch.');
        }

        $data = json_decode($payload, true) ?? [];
        $type = $data['type'] ?? '';
        $object = $data['data']['object'] ?? [];

        // Handle refund events
        $isRefundEvent = in_array($type, ['refund.created', 'refund.updated', 'refund.failed'], true);
        if ($isRefundEvent) {
            // Normalize Stripe refund status to our common values
            $refundStatus = match ($object['status'] ?? '') {
                'succeeded' => 'completed',
                'pending' => 'processing',
                'requires_action' => 'processing',
                'failed' => 'failed',
                default => 'pending',
            };

            return [
                'event_type' => $type,
                'refund_id' => $object['id'] ?? null,
                'gateway_payment_id' => $object['payment_intent'] ?? null,
                'status' => $refundStatus,
                'amount' => isset($object['amount']) ? ((float) $object['amount']) / 100 : 0.0,
                'currency' => strtoupper($object['currency'] ?? ''),
                'raw' => $data,
            ];
        }

        $status = match ($type) {
            'checkout.session.completed' => 'success',
            'checkout.session.expired' => 'failed',
            default => 'pending',
        };

        // Both checkout.session.completed and checkout.session.expired carry the session object
        // which uses amount_total (not amount). Handle both the same way.
        if (in_array($type, ['checkout.session.completed', 'checkout.session.expired'], true)) {
            return [
                'event_type' => $type,
                'gateway_payment_id' => $object['payment_intent'] ?? null,
                'gateway_order_id' => $object['id'] ?? null,
                'gateway_event_id' => $data['id'] ?? null,
                'status' => $status,
                'amount' => isset($object['amount_total']) ? ((float) $object['amount_total']) / 100 : 0.0,
                'currency' => strtoupper($object['currency'] ?? ''),
                'raw' => $data,
            ];
        }

        return [
            'event_type' => $type,
            'gateway_payment_id' => $object['id'] ?? null,
            'gateway_order_id' => $object['id'] ?? null,
            'gateway_event_id' => $data['id'] ?? null,
            'status' => $status,
            'amount' => isset($object['amount']) ? ((float) $object['amount']) / 100 : 0.0,
            'currency' => strtoupper($object['currency'] ?? ''),
            'raw' => $data,
        ];
    }

    public function getPaymentStatus(?string $gatewayPaymentId, ?string $gatewayOrderId = null): array
    {
        $settings = $this->requireSettings();

        // PaymentIntent id is only known once a webhook has processed the checkout
        // session — before that, fall back to checking the Checkout Session itself
        // (gateway_order_id), which exists from the moment the payment was created.
        if (! $gatewayPaymentId) {
            return $this->getStatusFromCheckoutSession($settings, $gatewayOrderId);
        }

        $response = Http::withToken($settings->api_secret)
            ->get($this->baseUrl().'/payment_intents/'.$gatewayPaymentId);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Stripe status fetch failed: '.$response->body());
        }

        $data = $response->json();

        $status = match ($data['status'] ?? '') {
            'succeeded' => 'success',
            'canceled', 'requires_payment_method' => 'failed',
            default => 'pending',
        };

        return [
            'status' => $status,
            'amount' => isset($data['amount']) ? ((float) $data['amount']) / 100 : 0.0,
            'paid_at' => isset($data['created']) ? date('c', (int) $data['created']) : null,
            'raw' => $data,
        ];
    }

    /**
     * @return array{status: string, amount: float, paid_at: ?string, gateway_payment_id?: ?string, raw: array<string, mixed>}
     */
    private function getStatusFromCheckoutSession(PaymentGatewaySetting $settings, ?string $gatewayOrderId): array
    {
        if (! $gatewayOrderId) {
            throw new PaymentGatewayException('Stripe status fetch failed: no gateway payment or session id available.');
        }

        $response = Http::withToken($settings->api_secret)
            ->get($this->baseUrl().'/checkout/sessions/'.$gatewayOrderId);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Stripe status fetch failed: '.$response->body());
        }

        $data = $response->json();

        $status = match (true) {
            ($data['payment_status'] ?? null) === 'paid' => 'success',
            ($data['status'] ?? null) === 'expired' => 'failed',
            default => 'pending',
        };

        return [
            'status' => $status,
            'amount' => isset($data['amount_total']) ? ((float) $data['amount_total']) / 100 : 0.0,
            'paid_at' => isset($data['created']) ? date('c', (int) $data['created']) : null,
            'gateway_payment_id' => is_string($data['payment_intent'] ?? null) ? $data['payment_intent'] : null,
            'raw' => $data,
        ];
    }

    public function cancelPayment(?string $gatewayPaymentId, ?string $gatewayOrderId = null): void
    {
        // No-op: unlike Razorpay, a retried Stripe Checkout Session's id (gateway_order_id)
        // round-trips unchanged through the eventual webhook, so a late payment on a stale
        // session still resolves to the correct Payment row via findPaymentForWebhook()'s
        // gateway_order_id match. There's no unmatchable-payment risk here to close.
    }

    public function refundPayment(string $gatewayPaymentId, float $amount, ?string $reason = null): array
    {
        $settings = $this->requireSettings();

        $response = Http::withToken($settings->api_secret)
            ->asForm()
            ->post($this->baseUrl().'/refunds', [
                'payment_intent' => $gatewayPaymentId,
                'amount' => (int) round($amount * 100),
                'reason' => $reason ?: 'requested_by_customer',
            ]);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Stripe refund failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'refund_id' => $data['id'] ?? '',
            'status' => $data['status'] ?? 'pending',
            'raw' => $data,
        ];
    }

    public function getRefundStatus(string $refundId, ?string $gatewayPaymentId = null): array
    {
        $settings = $this->requireSettings();

        $response = Http::withToken($settings->api_secret)
            ->get($this->baseUrl().'/refunds/'.$refundId);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Stripe refund status fetch failed: '.$response->body());
        }

        $data = $response->json();

        $status = match ($data['status'] ?? '') {
            'succeeded' => 'completed',
            'failed', 'canceled' => 'failed',
            default => 'processing',
        };

        return [
            'status' => $status,
            'raw' => $data,
        ];
    }
}
