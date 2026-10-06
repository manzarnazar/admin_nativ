<?php

namespace App\Services\Payments\Contracts;

use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\Exceptions\SignatureVerificationException;

interface PaymentProviderInterface
{
    /**
     * Create a payment order/intent with the gateway.
     *
     * @param  Payment  $payment  Local payment record with amount, currency, metadata.
     * @return array{
     *     payment_url?: string,
     *     payment_token?: string,
     *     gateway_order_id: string,
     *     gateway_payment_id?: string,
     *     raw: array<string, mixed>
     * }
     */
    public function createPayment(Payment $payment): array;

    /**
     * Verify webhook signature and extract normalized payment data.
     *
     * @param  string  $payload  Raw webhook payload (exact bytes).
     * @param  array<string, string>  $headers  Request headers (lowercased keys preferred).
     * @return array{
     *     gateway_payment_id: ?string,
     *     gateway_order_id: ?string,
     *     gateway_event_id: ?string,
     *     status: string,
     *     amount: float,
     *     currency: string,
     *     raw: array<string, mixed>
     * }
     *
     * @throws SignatureVerificationException
     */
    public function verifyWebhook(string $payload, array $headers): array;

    /**
     * Get payment status from gateway (used by reconciliation job).
     *
     * At least one of $gatewayPaymentId / $gatewayOrderId must be given. Some gateways
     * (e.g. Stripe Checkout) only populate the payment id once a webhook has processed,
     * so $gatewayOrderId is the fallback identifier reconciliation can still check by.
     *
     * @return array{
     *     status: string,
     *     amount: float,
     *     paid_at: ?string,
     *     gateway_payment_id?: ?string,
     *     raw: array<string, mixed>
     * }
     */
    public function getPaymentStatus(?string $gatewayPaymentId, ?string $gatewayOrderId = null): array;

    /**
     * Cancel/void a not-yet-paid payment at the gateway, if the gateway supports it.
     *
     * Best-effort by design: implementations should treat "already paid" or "already
     * expired/cancelled" as a no-op rather than an error — the caller only wants to
     * ensure the payment can no longer be completed, and both of those states already
     * guarantee that. Gateways with no equivalent concept (or where this isn't needed
     * for correctness) may implement this as a no-op.
     */
    public function cancelPayment(?string $gatewayPaymentId, ?string $gatewayOrderId = null): void;

    /**
     * Refund a payment (partial or full).
     *
     * @return array{refund_id: string, status: string, raw: array<string, mixed>}
     */
    public function refundPayment(string $gatewayPaymentId, float $amount, ?string $reason = null): array;

    /**
     * Get refund status from gateway (used by the refund reconciliation job).
     *
     * @param  string  $refundId  Gateway refund identifier.
     * @param  string|null  $gatewayPaymentId  Original gateway payment/transaction id (required by some gateways, e.g. Flutterwave).
     * @return array{status: string, raw: array<string, mixed>}
     */
    public function getRefundStatus(string $refundId, ?string $gatewayPaymentId = null): array;

    /**
     * Attach settings for the gateway (credentials, mode, etc.).
     */
    public function setSettings(PaymentGatewaySetting $settings): static;
}
