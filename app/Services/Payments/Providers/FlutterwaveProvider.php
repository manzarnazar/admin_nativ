<?php

namespace App\Services\Payments\Providers;

use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\Exceptions\PaymentGatewayException;
use App\Services\Payments\Exceptions\SignatureVerificationException;
use Illuminate\Support\Facades\Http;

//

class FlutterwaveProvider extends BasePaymentProvider
{
    protected function baseUrl(): string
    {
        return 'https://api.flutterwave.com/v3';
    }

    public function createPayment(Payment $payment): array
    {
        $settings = $this->requireSettings();

        $txRef = 'payment_'.$payment->id.'_'.uniqid();

        $response = Http::withToken($settings->api_secret)
            ->asJson()
            ->post($this->baseUrl().'/payments', [
                'tx_ref' => $txRef,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'redirect_url' => route('payments.flutterwave.callback', ['booking' => $payment->booking_id]),
                'customer' => [
                    'email' => $payment->user?->email ?: 'customer@example.com',
                    'name' => $payment->user?->name ?: 'Customer',
                ],
                'meta' => [
                    'booking_id' => (string) $payment->booking_id,
                    'payment_id' => (string) $payment->id,
                ],
                'customizations' => [
                    'title' => 'Booking #'.$payment->booking_id,
                ],
            ]);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Flutterwave payment creation failed: '.$response->body());
        }

        $data = $response->json();
        $link = $data['data']['link'] ?? null;
        // Prefer the echoed-back tx_ref from the API; fall back to the value we sent
        $txRef = $data['data']['tx_ref'] ?? ($data['tx_ref'] ?? $txRef);

        return [
            'gateway_order_id' => (string) $txRef,
            'payment_url' => $link,
            'payment_token' => (string) $txRef,
            'raw' => $data,
        ];
    }

    public function verifyWebhook(string $payload, array $headers): array
    {
        $settings = $this->requireSettings();
        $signature = $headers['verif-hash'] ?? $headers['Verif-Hash'] ?? null;

        if (! $signature) {
            throw new SignatureVerificationException('Flutterwave verif-hash header missing.');
        }

        if (! hash_equals((string) $settings->webhook_secret, (string) $signature)) {
            throw new SignatureVerificationException('Flutterwave signature mismatch.');
        }

        $data = json_decode($payload, true) ?? [];
        $event = $data['event'] ?? ($data['data']['status'] ?? '');
        $dataBlock = $data['data'] ?? [];

        // Handle refund events
        $isRefundEvent = $event === 'refund.completed' || $event === 'refund.failed' || ($dataBlock['status'] ?? '') === 'refund';
        if ($isRefundEvent) {
            return [
                'event_type' => $event,
                'refund_id' => $dataBlock['id'] ?? null,
                'gateway_payment_id' => $dataBlock['transaction_id'] ?? null,
                'status' => $dataBlock['status'] ?? 'pending',
                'amount' => (float) ($dataBlock['amount'] ?? 0),
                'currency' => $dataBlock['currency'] ?? '',
                'raw' => $data,
            ];
        }

        $status = match (true) {
            $event === 'charge.completed' && ($dataBlock['status'] ?? '') === 'successful' => 'success',
            ($dataBlock['status'] ?? '') === 'successful' => 'success',
            ($dataBlock['status'] ?? '') === 'failed' => 'failed',
            default => 'pending',
        };

        return [
            'event_type' => $event,
            'gateway_payment_id' => isset($dataBlock['id']) ? (string) $dataBlock['id'] : null,
            'gateway_order_id' => $dataBlock['tx_ref'] ?? null,
            // flw_ref is Flutterwave's unique event/transaction reference (e.g. FLW-MOCK-xxx),
            // distinct from the numeric transaction id used as gateway_payment_id
            'gateway_event_id' => $dataBlock['flw_ref'] ?? (isset($dataBlock['id']) ? (string) $dataBlock['id'] : null),
            'status' => $status,
            'amount' => (float) ($dataBlock['amount'] ?? 0),
            'currency' => $dataBlock['currency'] ?? '',
            'raw' => $data,
        ];
    }

    public function getPaymentStatus(?string $gatewayPaymentId, ?string $gatewayOrderId = null): array
    {
        $settings = $this->requireSettings();

        // Flutterwave's own transaction id is only known once a webhook has processed
        // the payment — before that, fall back to verifying by our tx_ref (gateway_order_id),
        // which exists from the moment the payment was created.
        if (! $gatewayPaymentId) {
            return $this->getStatusByReference($settings, $gatewayOrderId);
        }

        $response = Http::withToken($settings->api_secret)
            ->get($this->baseUrl().'/transactions/'.$gatewayPaymentId.'/verify');

        if (! $response->successful()) {
            throw new PaymentGatewayException('Flutterwave status fetch failed: '.$response->body());
        }

        $data = $response->json();
        $txStatus = $data['data']['status'] ?? '';

        $status = match ($txStatus) {
            'successful' => 'success',
            'failed' => 'failed',
            default => 'pending',
        };

        return [
            'status' => $status,
            'amount' => (float) ($data['data']['amount'] ?? 0),
            'paid_at' => $data['data']['created_at'] ?? null,
            'raw' => $data,
        ];
    }

    /**
     * @return array{status: string, amount: float, paid_at: ?string, gateway_payment_id?: ?string, raw: array<string, mixed>}
     */
    private function getStatusByReference(PaymentGatewaySetting $settings, ?string $gatewayOrderId): array
    {
        if (! $gatewayOrderId) {
            throw new PaymentGatewayException('Flutterwave status fetch failed: no gateway payment or reference id available.');
        }

        $response = Http::withToken($settings->api_secret)
            ->get($this->baseUrl().'/transactions/verify_by_reference', ['tx_ref' => $gatewayOrderId]);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Flutterwave status fetch failed: '.$response->body());
        }

        $data = $response->json();
        $txStatus = $data['data']['status'] ?? '';

        $status = match ($txStatus) {
            'successful' => 'success',
            'failed' => 'failed',
            default => 'pending',
        };

        return [
            'status' => $status,
            'amount' => (float) ($data['data']['amount'] ?? 0),
            'paid_at' => $data['data']['created_at'] ?? null,
            'gateway_payment_id' => isset($data['data']['id']) ? (string) $data['data']['id'] : null,
            'raw' => $data,
        ];
    }

    public function cancelPayment(?string $gatewayPaymentId, ?string $gatewayOrderId = null): void
    {
        // No-op: like Stripe, a retried Flutterwave payment's tx_ref (gateway_order_id)
        // round-trips unchanged through the eventual webhook, so a late payment on a
        // stale checkout still resolves to the correct Payment row. No unmatchable-
        // payment risk here to close.
    }

    public function refundPayment(string $gatewayPaymentId, float $amount, ?string $reason = null): array
    {
        $settings = $this->requireSettings();

        $response = Http::withToken($settings->api_secret)
            ->asJson()
            ->post($this->baseUrl().'/transactions/'.$gatewayPaymentId.'/refund', [
                'amount' => $amount,
            ]);

        if (! $response->successful()) {
            throw new PaymentGatewayException('Flutterwave refund failed: '.$response->body());
        }

        $data = $response->json();

        return [
            'refund_id' => (string) ($data['data']['id'] ?? ''),
            'status' => $data['data']['status'] ?? 'pending',
            'raw' => $data,
        ];
    }

    public function getRefundStatus(string $refundId, ?string $gatewayPaymentId = null): array
    {
        $settings = $this->requireSettings();

        if (! $gatewayPaymentId) {
            throw new PaymentGatewayException('Flutterwave refund status requires the original transaction id.');
        }

        $response = Http::withToken($settings->api_secret)
            ->get($this->baseUrl().'/transactions/'.$gatewayPaymentId.'/refunds');

        if (! $response->successful()) {
            throw new PaymentGatewayException('Flutterwave refund status fetch failed: '.$response->body());
        }

        $data = $response->json();

        $match = null;
        foreach ($data['data'] ?? [] as $refund) {
            if ((string) ($refund['id'] ?? '') === (string) $refundId) {
                $match = $refund;
                break;
            }
        }

        $status = match ($match['status'] ?? '') {
            'completed' => 'completed',
            'failed' => 'failed',
            default => 'processing',
        };

        return [
            'status' => $status,
            'raw' => $data,
        ];
    }
}
