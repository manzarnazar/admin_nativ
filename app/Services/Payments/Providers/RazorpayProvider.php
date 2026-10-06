<?php

namespace App\Services\Payments\Providers;

use App\Models\Payment;
use App\Services\Payments\Exceptions\PaymentGatewayException;
use App\Services\Payments\Exceptions\SignatureVerificationException;
use Illuminate\Support\Facades\Http;

class RazorpayProvider extends BasePaymentProvider
{
    protected function baseUrl(): string
    {
        return 'https://api.razorpay.com/v1';
    }

    /**
     * Format phone number for Razorpay (must be 8-14 digits long, no special characters). Returns null if invalid or not provided.
     */
    private function formatPhoneNumber(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }

        // Remove all non-digit characters
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // If it's too short or invalid, return null
        if (strlen($digits) < 8 || strlen($digits) > 14) {
            return null;
        }

        return $digits;
    }

    public function createPayment(Payment $payment): array
    {
        $settings = $this->requireSettings();

        // Create order first
        $response = Http::withBasicAuth($settings->api_key, $settings->api_secret)
            ->asJson()
            ->post($this->baseUrl().'/orders', [
                'amount' => (int) round($payment->amount * 100), // Razorpay uses paise/cents
                'currency' => $payment->currency,
                'receipt' => 'payment_'.$payment->id,
                'notes' => [
                    'booking_id' => (string) $payment->booking_id,
                    'payment_id' => (string) $payment->id,
                ],
            ]);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Razorpay order creation failed: '.$response->body());
        }

        $orderData = $response->json();
        $orderId = $orderData['id'];

        // Create payment link/checkout for webview
        $linkResponse = Http::withBasicAuth($settings->api_key, $settings->api_secret)
            ->asJson()
            ->post($this->baseUrl().'/payment_links', [
                'amount' => (int) round($payment->amount * 100),
                'currency' => $payment->currency,
                'accept_partial' => false,
                'description' => 'Payment for Booking '.$payment->booking->booking_number ?? $payment->booking_id,
                'customer' => [
                    'name' => $payment->booking->guest_name ?? 'Guest',
                    'email' => $payment->booking->guest_email ?? '',
                    'contact' => $this->formatPhoneNumber($payment->booking->guest_phone ?? null),
                ],
                'notes' => [
                    'booking_id' => (string) $payment->booking_id,
                    'payment_id' => (string) $payment->id,
                    'order_id' => $orderId,
                ],
                'reference_id' => $orderId,
                'expire_by' => now()->addMinutes(30)->timestamp,
                'callback_url' => route('payments.razorpay.callback', ['booking' => $payment->booking_id]),
                'callback_method' => 'get',
            ]);

        if (! $linkResponse->successful()) {
            throw new PaymentGatewayException('Razorpay payment link creation failed: '.$linkResponse->body());
        }

        $linkData = $linkResponse->json();

        // Log for debugging
        \Log::info('Razorpay payment link response', ['response' => $linkData]);

        return [
            'gateway_order_id' => $orderId,
            'gateway_payment_id' => $linkData['id'] ?? null,
            'payment_url' => $linkData['short_url'] ?? null,
            'payment_token' => $linkData['id'] ?? $orderId,
            'raw' => array_merge($orderData, ['payment_link' => $linkData]),
        ];
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $settings = $this->requireSettings();
        $signature = $headers['x-razorpay-signature'] ?? $headers['X-Razorpay-Signature'] ?? null;

        if (! $signature) {
            throw new SignatureVerificationException('Razorpay signature header missing.');
        }

        $expected = hash_hmac('sha256', $payload, $settings->webhook_secret);

        if (! hash_equals($expected, $signature)) {
            throw new SignatureVerificationException('Razorpay signature mismatch.');
        }

        $data = json_decode($payload, true);

        // Extract data based on event type
        $isPaymentLinkEvent = str_starts_with($data['event'] ?? '', 'payment_link.');
        $isRefundEvent = str_starts_with($data['event'] ?? '', 'refund.');

        if ($isRefundEvent) {
            // For refund events, extract refund entity
            $refundEntity = $data['payload']['refund']['entity'] ?? [];

            return [
                'event_type' => $data['event'] ?? null,
                'refund_id' => $refundEntity['id'] ?? null,
                'gateway_payment_id' => $refundEntity['payment_id'] ?? null,
                'status' => $refundEntity['status'] ?? 'pending',
                'amount' => isset($refundEntity['amount']) ? ((float) $refundEntity['amount']) / 100 : 0.0,
                'currency' => $refundEntity['currency'] ?? null,
                'raw' => $data,
            ];
        }

        $paymentEntity = $isPaymentLinkEvent
            ? ($data['payload']['payment']['entity'] ?? $data['payload']['payment_link']['entity'] ?? [])
            : ($data['payload']['payment']['entity'] ?? []);

        return [
            'event_type' => $data['event'] ?? null,
            'gateway_payment_id' => $paymentEntity['id'] ?? $data['payload']['payment_link']['entity']['id'] ?? null,
            'gateway_order_id' => $paymentEntity['order_id'] ?? $data['payload']['payment_link']['entity']['order_id'] ?? $data['payload']['payment_link']['entity']['reference_id'] ?? null,
            'status' => $this->mapStatus($data['event'] ?? null),
            'amount' => isset($paymentEntity['amount']) ? ((float) $paymentEntity['amount']) / 100 : (isset($data['payload']['payment_link']['entity']['amount']) ? ((float) $data['payload']['payment_link']['entity']['amount']) / 100 : 0.0),
            'currency' => $paymentEntity['currency'] ?? $data['payload']['payment_link']['entity']['currency'] ?? null,
            'raw' => $data,
        ];
    }

    private function mapStatus(?string $event): string
    {
        return match ($event) {
            'payment.captured', 'payment_link.paid' => 'success',
            'payment.failed', 'payment_link.cancelled' => 'failed',
            default => 'pending',
        };
    }

    public function getPaymentStatus(?string $gatewayPaymentId, ?string $gatewayOrderId = null): array
    {
        if (! $gatewayPaymentId) {
            throw new PaymentGatewayException('Razorpay status fetch failed: no gateway payment id available.');
        }

        $settings = $this->requireSettings();

        $response = Http::withBasicAuth($settings->api_key, $settings->api_secret)
            ->get($this->baseUrl().'/payments/'.$gatewayPaymentId);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Razorpay status fetch failed: '.$response->body());
        }

        $data = $response->json();

        $status = match ($data['status'] ?? '') {
            'captured', 'authorized' => 'success',
            'failed' => 'failed',
            default => 'pending',
        };

        return [
            'status' => $status,
            'amount' => isset($data['amount']) ? ((float) $data['amount']) / 100 : 0.0,
            'paid_at' => isset($data['created_at']) ? date('c', (int) $data['created_at']) : null,
            'raw' => $data,
        ];
    }

    public function cancelPayment(?string $gatewayPaymentId, ?string $gatewayOrderId = null): void
    {
        // $gatewayPaymentId is the payment link id (plink_...) for this gateway — that's
        // the object that stays live/payable independently of our own order, so it's
        // the one that actually needs cancelling. Nothing to cancel without it.
        if (! $gatewayPaymentId) {
            return;
        }

        $settings = $this->requireSettings();

        $response = Http::withBasicAuth($settings->api_key, $settings->api_secret)
            ->post($this->baseUrl().'/payment_links/'.$gatewayPaymentId.'/cancel');

        // Razorpay returns 400 for "already paid" and "already expired/cancelled" —
        // both mean the link can no longer be completed, which is exactly the outcome
        // we want, so treat them as success rather than an error.
        if ($response->successful() || $response->status() === 400) {
            return;
        }

        throw new PaymentGatewayException('Razorpay payment link cancellation failed: '.$response->body());
    }

    public function refundPayment(string $gatewayPaymentId, float $amount, ?string $reason = null): array
    {

        $settings = $this->requireSettings();

        $response = Http::withBasicAuth($settings->api_key, $settings->api_secret)
            ->asJson()
            ->post($this->baseUrl().'/payments/'.$gatewayPaymentId.'/refund', [
                'amount' => (int) round($amount * 100),
                'notes' => $reason ? ['reason' => $reason] : [],
            ]);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Razorpay refund failed: '.$response->body());
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

        $response = Http::withBasicAuth($settings->api_key, $settings->api_secret)
            ->get($this->baseUrl().'/refunds/'.$refundId);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Razorpay refund status fetch failed: '.$response->body());
        }

        $data = $response->json();

        $status = match ($data['status'] ?? '') {
            'processed' => 'completed',
            'failed' => 'failed',
            default => 'processing',
        };

        return [
            'status' => $status,
            'raw' => $data,
        ];
    }
}
