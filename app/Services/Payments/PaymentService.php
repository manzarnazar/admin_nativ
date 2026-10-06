<?php

namespace App\Services\Payments;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Enums\RefundStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Models\ProcessedWebhookEvent;
use App\Models\Refund;
use App\Services\BookingService;
use App\Services\NotificationService;
use App\Services\Payments\Exceptions\PaymentGatewayException;
use App\Services\Payments\Exceptions\SignatureVerificationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    public function __construct(
        protected BookingService $bookingService,
        protected PaymentProviderFactory $providerFactory,
    ) {}

    /**
     * Create a payment and generate gateway order.
     *
     *
     * @param  array{booking_id: int, gateway_type: string, payment_type: string, amount: float, currency: string, country_id: int}  $data
     */
    public function createPayment(array $data): Payment
    {
        $gatewayType = PaymentGateway::from($data['gateway_type']);
        $paymentType = PaymentType::from($data['payment_type']);
        $countryId = $data['country_id'];

        // Get gateway settings for this country
        $settings = PaymentGatewaySetting::query()
            ->where('gateway_type', $gatewayType->value)
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->first();

        if (! $settings) {
            throw new PaymentGatewayException("No active settings for gateway '{$gatewayType->value}' in country {$countryId}");
        }

        // Validate booking exists and is in PENDING status
        $booking = Booking::findOrFail($data['booking_id']);
        if (! in_array($booking->status->value, [BookingStatus::Pending->value, BookingStatus::PendingPayment->value])) {
            throw new PaymentGatewayException('Booking must be in Pending or Pending Payment status to create payment.');
        }

        $payment = Payment::create([
            'booking_id' => $data['booking_id'],
            'user_id' => auth()->id(),
            'gateway_type' => $gatewayType->value,
            'amount' => $data['amount'],
            'currency' => $data['currency'],
            'payment_type' => $paymentType->value,
            'remaining_amount' => $paymentType === PaymentType::Partial ? ($data['total_amount'] - $data['amount']) : null,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        try {
            // Create gateway order
            $provider = $this->providerFactory->make($gatewayType, $settings);
            $result = $provider->createPayment($payment);

            $payment->update([
                'gateway_order_id' => $result['gateway_order_id'],
                'gateway_payment_id' => $result['gateway_payment_id'] ?? null,
                'gateway_response' => array_merge($result['raw'], ['payment_url' => $result['payment_url'] ?? null]),
            ]);

            return $payment->fresh();
        } catch (PaymentGatewayException $e) {
            // Mark payment as failed on gateway error
            $payment->update([
                'status' => PaymentTransactionStatus::Failed,
                'failed_at' => now(),
                'gateway_response' => ['error' => $e->getMessage()],
            ]);

            Log::error('Gateway payment creation failed', [
                'payment_id' => $payment->id,
                'booking_id' => $payment->booking_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Retry a failed payment (create new payment record for same booking).
     */
    public function retryPayment(Payment $originalPayment): Payment
    {
        $booking = $originalPayment->booking;
        $settings = PaymentGatewaySetting::query()
            ->where('gateway_type', $originalPayment->gateway_type)
            ->where('country_id', $booking->property->country_id)
            ->where('is_active', true)
            ->first();

        if (! $settings) {
            throw new PaymentGatewayException('Gateway settings not found for retry.');
        }

        $payment = Payment::create([
            'booking_id' => $originalPayment->booking_id,
            'user_id' => $originalPayment->user_id,
            'gateway_type' => $originalPayment->gateway_type,
            'amount' => $originalPayment->amount,
            'currency' => $originalPayment->currency,
            'payment_type' => $originalPayment->payment_type,
            'remaining_amount' => $originalPayment->remaining_amount,
            'status' => PaymentTransactionStatus::Pending,
        ]);

        try {
            $provider = $this->providerFactory->make($originalPayment->gateway_type, $settings);
            $result = $provider->createPayment($payment);

            $payment->update([
                'gateway_order_id' => $result['gateway_order_id'],
                'gateway_payment_id' => $result['gateway_payment_id'] ?? null,
                'gateway_response' => array_merge($result['raw'], ['payment_url' => $result['payment_url'] ?? null]),
            ]);

            return $payment->fresh();
        } catch (PaymentGatewayException $e) {
            // Mark payment as failed on gateway error
            $payment->update([
                'status' => PaymentTransactionStatus::Failed,
                'failed_at' => now(),
                'gateway_response' => ['error' => $e->getMessage()],
            ]);

            Log::error('Gateway payment retry failed', [
                'payment_id' => $payment->id,
                'original_payment_id' => $originalPayment->id,
                'booking_id' => $payment->booking_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Cancel a pending payment.
     */
    public function cancelPayment(Payment $payment): void
    {
        if (! $payment->isPending()) {
            throw new PaymentGatewayException('Only pending payments can be cancelled.');
        }

        $payment->update([
            'status' => PaymentTransactionStatus::Cancelled,
        ]);
    }

    /**
     * Mark a still-pending payment as Failed because the user is explicitly retrying,
     * and best-effort cancel it at the gateway so a stale, still-payable link/session
     * can't be completed later and go unmatched by a webhook. Gateway cancellation
     * failures are logged but never block the retry — cancellation is cleanup, not a
     * hard requirement. findPaymentForWebhook()'s notes-based match is the second
     * line of defense if cancellation didn't happen in time.
     */
    public function failPaymentForRetry(Payment $payment): void
    {
        if (! $payment->isPending()) {
            throw new PaymentGatewayException('Only pending payments can be failed for retry.');
        }

        try {
            $countryId = $payment->booking?->property?->country_id;

            $settings = $countryId
                ? PaymentGatewaySetting::query()
                    ->where('gateway_type', $payment->gateway_type)
                    ->where('country_id', $countryId)
                    ->where('is_active', true)
                    ->first()
                : null;

            if ($settings) {
                $provider = $this->providerFactory->make($payment->gateway_type, $settings);
                $provider->cancelPayment($payment->gateway_payment_id, $payment->gateway_order_id);
            }
        } catch (\Throwable $e) {
            Log::warning('Gateway payment cancellation failed during retry (continuing anyway)', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
        }

        $payment->update(['status' => PaymentTransactionStatus::Failed]);
    }

    /**
     * Process refund webhook from gateway.
     *
     * @param  string  $gatewayType  razorpay|stripe|flutterwave
     * @param  string  $payload  Raw webhook body
     * @param  array<string, string>  $headers  Request headers
     */
    public function processRefundWebhook(string $gatewayType, string $payload, array $headers): void
    {
        try {
            ['data' => $data, 'settings' => $settings] = $this->resolveSettingsAndVerifyWebhook($gatewayType, $payload, $headers);
            //  Log::info('Refund webhook parsed', [
            //                 'gateway' => $gatewayType,
            //                 'event_type' => $data['event_type'] ?? null,
            //                 'refund_id' => $data['refund_id'] ?? null,
            //                 'payment_id' => $data['gateway_payment_id'] ?? null,
            //                 'keys' => array_keys($data),
            //             ]);
            // Extract event id for idempotency
            // Razorpay: from header x-razorpay-event-id
            // Stripe/Flutterwave: from verified payload
            $eventId = $headers['x-razorpay-event-id'] ?? $data['event_id'] ?? null;

            if (! $eventId) {
                Log::warning('Webhook event id not found', ['gateway' => $gatewayType]);

                return;
            }

            // Find refund using gateway refund_id
            $refund = Refund::where('refund_id', $data['refund_id'] ?? null)->first();

            if (! $refund) {
                Log::warning('Refund not found for webhook', $data);

                return;
            }

            // Idempotency check - don't update if already completed
            if ($refund->status === RefundStatus::Completed) {
                Log::info('Refund already completed, ignoring duplicate', ['refund_id' => $refund->id, 'event_id' => $eventId]);

                return;
            }

            // Transaction: insert dedup record and update refund atomically
            $processed = DB::transaction(function () use ($gatewayType, $settings, $eventId, $data, $refund) {
                // Try to record event first for idempotency (atomic insert before side effects)
                try {
                    ProcessedWebhookEvent::create([
                        'gateway' => $gatewayType,
                        'payment_gateway_setting_id' => $settings->id,
                        'event_id' => $eventId,
                        'event_type' => $data['event_type'] ?? null,
                        'processed_at' => now(),
                    ]);
                } catch (QueryException $e) {
                    // SQLSTATE 23000 = integrity constraint violation (unique key)
                    if ($e->getCode() === '23000') {
                        Log::info('Webhook event already processed (duplicate)', [
                            'gateway' => $gatewayType,
                            'event_id' => $eventId,
                        ]);

                        return false; // Indicate duplicate, not processed
                    }
                    throw $e;
                }

                // Update refund status based on gateway status
                $status = match ($data['status'] ?? 'pending') {
                    'processed', 'succeeded', 'success', 'completed' => RefundStatus::Completed,
                    'failed' => RefundStatus::Failed,
                    default => RefundStatus::Processing,
                };

                // Safely merge gateway responses
                $existingResponse = is_array($refund->gateway_response) ? $refund->gateway_response : [];
                $newResponse = is_array($data['raw'] ?? null) ? $data['raw'] : ['raw' => $data['raw'] ?? null];

                $refund->update([
                    'status' => $status,
                    'gateway_response' => array_merge($existingResponse, $newResponse),
                    'processed_at' => $status === RefundStatus::Completed ? now() : $refund->processed_at,
                ]);

                // Create refund payment record for transaction history
                if ($status === RefundStatus::Completed) {
                    $originalPayment = $refund->payment;

                    Payment::create([
                        'booking_id' => $originalPayment->booking_id,
                        'user_id' => $originalPayment->user_id,
                        'gateway_type' => $originalPayment->gateway_type,
                        'gateway_payment_id' => $refund->refund_id,
                        'amount' => $refund->amount,
                        'currency' => $originalPayment->currency,
                        'payment_type' => PaymentType::Refund,
                        'status' => PaymentTransactionStatus::Refunded,
                        'paid_at' => now(),
                    ]);
                }

                return true; // Indicate successfully processed
            });

            // Only log if actually processed (not duplicate)
            if ($processed) {
                $refund->refresh(); // Get latest state from database
                Log::info('Refund webhook processed', [
                    'refund_id' => $refund->id,
                    'status' => $refund->status->value,
                    'event_id' => $eventId,
                ]);

                if (in_array($refund->status, [RefundStatus::Completed, RefundStatus::Failed], true)) {
                    app(NotificationService::class)->sendRefundNotification($refund, $refund->status);
                }
            }
        } catch (SignatureVerificationException $e) {
            Log::error('Refund webhook signature verification failed', [
                'gateway' => $gatewayType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Refund webhook processing failed', [
                'gateway' => $gatewayType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Process webhook from gateway.
     *
     * @param  string  $gatewayType  razorpay|stripe|flutterwave
     * @param  string  $payload  Raw webhook body
     * @param  array<string, string>  $headers  Request headers
     */
    public function processWebhook(string $gatewayType, string $payload, array $headers): void
    {
        try {
            ['data' => $data, 'settings' => $settings] = $this->resolveSettingsAndVerifyWebhook($gatewayType, $payload, $headers);

            $this->recordWebhookReceived($gatewayType, $settings->id, $headers, $data);

            // Find payment using fallback strategy
            $payment = $this->findPaymentForWebhook($data);

            if (! $payment) {
                Log::warning('Payment not found for webhook', $data);

                return;
            }

            // Idempotency checks
            if ($payment->processed_at !== null) {
                Log::info('Payment already processed', ['payment_id' => $payment->id]);

                return;
            }

            if ($payment->status === PaymentTransactionStatus::Success) {
                Log::info('Payment already success, ignoring duplicate', ['payment_id' => $payment->id]);

                return;
            }

            // Amount validation only applies to successful payments — failed/expired
            // events don't carry a meaningful amount and should never be flagged.
            if ($data['status'] === 'success' && ! $this->validateWebhookAmount($payment, $data)) {
                $payment->update([
                    'status' => PaymentTransactionStatus::Flagged,
                ]);
                Log::warning('Payment amount mismatch, flagged for review', [
                    'payment_id' => $payment->id,
                    'expected' => $payment->amount,
                    'received' => $data['amount'],
                ]);

                return;
            }

            // Update payment based on gateway status
            $this->updatePaymentFromWebhook($payment, $data);
        } catch (SignatureVerificationException $e) {
            Log::error('Webhook signature verification failed', [
                'gateway' => $gatewayType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'gateway' => $gatewayType,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Best-effort observability log — records that a payment webhook was received and its
     * signature verified, purely to drive the "Payment Webhook Status" admin UI. Payment
     * idempotency is already handled separately via Payment::processed_at/status, so this must
     * never block processing: it swallows and logs ANY failure (not just the expected
     * duplicate-key collision from the same event being delivered twice) rather than letting an
     * unrelated logging error abort real payment confirmation, which runs right after this call.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $data
     */
    private function recordWebhookReceived(string $gatewayType, int $paymentGatewaySettingId, array $headers, array $data): void
    {
        $eventId = $headers['x-razorpay-event-id'] ?? $data['gateway_event_id'] ?? null;

        if (! $eventId) {
            return;
        }

        try {
            ProcessedWebhookEvent::create([
                'gateway' => $gatewayType,
                'payment_gateway_setting_id' => $paymentGatewaySettingId,
                'event_id' => $eventId,
                'event_type' => $data['event_type'] ?? null,
                'processed_at' => now(),
            ]);
        } catch (QueryException $e) {
            // SQLSTATE 23000 = duplicate event_id (gateway redelivered the same webhook) — expected, ignore.
            if ($e->getCode() !== '23000') {
                Log::warning('Failed to record webhook receipt (non-blocking)', [
                    'gateway' => $gatewayType,
                    'error' => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to record webhook receipt (non-blocking)', [
                'gateway' => $gatewayType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve which gateway settings row a webhook belongs to and verify its signature.
     *
     * A gateway can have multiple active settings rows (one per country), each with its
     * own webhook secret, all sharing the same webhook URL. There is no reliable way to
     * know which country a webhook belongs to before verifying it, so every active row
     * for this gateway is tried in turn until one produces a valid signature.
     *
     * @return array{settings: PaymentGatewaySetting, data: array<string, mixed>}
     *
     * @throws SignatureVerificationException if no active settings row's secret verifies
     * @throws PaymentGatewayException if no active settings row exists for this gateway
     */
    protected function resolveSettingsAndVerifyWebhook(string $gatewayType, string $payload, array $headers): array
    {
        $candidates = PaymentGatewaySetting::query()
            ->where('gateway_type', $gatewayType)
            ->where('is_active', true)
            ->get();

        if ($candidates->isEmpty()) {
            throw new PaymentGatewayException("Payment gateway settings not configured for {$gatewayType}");
        }

        $lastException = null;

        foreach ($candidates as $settings) {
            try {
                $provider = $this->providerFactory->make($gatewayType, $settings);

                return ['settings' => $settings, 'data' => $provider->verifyWebhook($payload, $headers)];
            } catch (SignatureVerificationException $e) {
                $lastException = $e;
            }
        }

        throw $lastException;
    }

    /**
     * Find payment using fallback strategy.
     */
    protected function findPaymentForWebhook(array $data): ?Payment
    {
        // Try gateway_payment_id first
        if (! empty($data['gateway_payment_id'])) {
            $payment = Payment::query()
                ->where('gateway_payment_id', $data['gateway_payment_id'])
                ->first();
            if ($payment) {
                return $payment;
            }
        }

        // Try gateway_order_id
        if (! empty($data['gateway_order_id'])) {
            $payment = Payment::query()
                ->where('gateway_order_id', $data['gateway_order_id'])
                ->first();
            if ($payment) {
                return $payment;
            }
        }

        // Fallback: Flutterwave sends payment_id directly in meta_data
        $flwPaymentId = $data['raw']['meta_data']['payment_id'] ?? null;
        if ($flwPaymentId) {
            $payment = Payment::query()->find((int) $flwPaymentId);
            if ($payment) {
                return $payment;
            }
        }

        // Fallback: match by our own internal payment id, when the gateway echoes it
        // back (Razorpay's notes, Stripe's metadata). Most precise fallback available —
        // an exact PK match, not a guess — so unlike the booking_id fallback below it
        // is NOT restricted to status=Pending. This is what recovers a payment whose
        // gateway_payment_id/gateway_order_id no longer match (e.g. Razorpay auto-
        // generating its own settlement order for a Payment Link) even after a retry
        // has locally flipped it to Failed — see failPaymentForRetry().
        $internalPaymentId = $data['raw']['payload']['payment']['entity']['notes']['payment_id'] ?? null
            ?? $data['raw']['payload']['order']['entity']['notes']['payment_id'] ?? null
            ?? $data['raw']['payload']['payment_link']['entity']['notes']['payment_id'] ?? null
            ?? $data['raw']['data']['object']['metadata']['payment_id'] ?? null;

        if ($internalPaymentId) {
            $payment = Payment::query()->find((int) $internalPaymentId);
            if ($payment) {
                return $payment;
            }
        }

        // Fallback: find by booking_id from notes (handle different payload structures)
        $bookingId = $data['raw']['notes']['booking_id']
            ?? $data['raw']['metadata']['booking_id']
            ?? $data['raw']['meta_data']['booking_id']
            ?? $data['raw']['payload']['payment']['entity']['notes']['booking_id'] ?? null
            ?? $data['raw']['payload']['payment_link']['entity']['notes']['booking_id'] ?? null;

        if ($bookingId) {
            $payment = Payment::query()
                ->where('booking_id', $bookingId)
                ->where('status', PaymentTransactionStatus::Pending)
                ->latest()
                ->first();
            if ($payment) {
                return $payment;
            }
        }

        return null;
    }

    /**
     * Validate webhook amount matches payment amount.
     * Providers already convert from paise/cents to rupees/dollars.
     */
    protected function validateWebhookAmount(Payment $payment, array $data): bool
    {
        // Amount is already converted by provider (paise/cents → rupees/dollars)
        $receivedAmount = (float) $data['amount'];

        return (float) $payment->amount === $receivedAmount
            && $payment->currency === $data['currency'];
    }

    /**
     * Update payment from webhook and trigger booking confirmation.
     */
    protected function updatePaymentFromWebhook(Payment $payment, array $data): void
    {
        DB::transaction(function () use ($payment, $data) {
            $status = match ($data['status']) {
                'success' => PaymentTransactionStatus::Success,
                'failed' => PaymentTransactionStatus::Failed,
                default => PaymentTransactionStatus::Pending,
            };

            // Update payment status first
            $payment->update([
                'status' => $status,
                'gateway_payment_id' => $data['gateway_payment_id'] ?? $payment->gateway_payment_id,
                'gateway_event_id' => $data['gateway_event_id'] ?? $payment->gateway_event_id,
                $status === PaymentTransactionStatus::Success ? 'paid_at' : 'failed_at' => now(),
                'gateway_response' => array_merge($payment->gateway_response ?? [], $data['raw']),
            ]);

            $bookingService = app(BookingService::class);

            if ($status === PaymentTransactionStatus::Success) {
                $bookingService->confirmBookingFromPayment($payment);
            } elseif ($status === PaymentTransactionStatus::Failed) {
                $booking = $payment->booking;
                if ($booking && ! in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Expired], true)) {
                    $bookingService->cancelBooking($booking);
                }
            }

            // Set processed_at AFTER successful processing
            $payment->update(['processed_at' => now()]);
        });
    }

    /**
     * Reconciliation job: Check pending payments with gateway.
     */
    public function reconcilePendingPayments(): void
    {
        $pendingPayments = Payment::query()
            ->where('status', PaymentTransactionStatus::Pending)
            ->where('created_at', '>', now()->subHours(1))
            ->whereHas('booking', fn ($q) => $q->where('status', '!=', BookingStatus::Expired))
            ->with('booking.property')
            ->get();

        foreach ($pendingPayments as $payment) {
            if ($payment->processed_at !== null) {
                continue; // Already processed by webhook
            }

            try {
                $countryId = $payment->booking?->property?->country_id;

                $settings = $countryId
                    ? PaymentGatewaySetting::query()
                        ->where('gateway_type', $payment->gateway_type)
                        ->where('country_id', $countryId)
                        ->where('is_active', true)
                        ->first()
                    : null;

                if (! $settings) {
                    Log::warning('Payment reconciliation skipped: gateway settings not found', [
                        'payment_id' => $payment->id,
                        'gateway_type' => $payment->gateway_type,
                    ]);

                    continue;
                }

                if (! $payment->gateway_payment_id && ! $payment->gateway_order_id) {
                    Log::warning('Payment reconciliation skipped: no gateway identifier available', [
                        'payment_id' => $payment->id,
                    ]);

                    continue;
                }

                $provider = $this->providerFactory->make($payment->gateway_type, $settings);
                $status = $provider->getPaymentStatus($payment->gateway_payment_id, $payment->gateway_order_id);

                if ($status['status'] === 'success') {
                    $this->updatePaymentFromWebhook($payment, [
                        'gateway_payment_id' => $status['gateway_payment_id'] ?? $payment->gateway_payment_id,
                        'gateway_order_id' => $payment->gateway_order_id,
                        'status' => 'success',
                        'amount' => $status['amount'],
                        'currency' => $payment->currency,
                        'raw' => $status['raw'],
                    ]);
                } elseif ($status['status'] === 'failed') {
                    DB::transaction(function () use ($payment) {
                        $payment->update([
                            'status' => PaymentTransactionStatus::Failed,
                            'failed_at' => now(),
                            'processed_at' => now(),
                        ]);

                        $booking = $payment->booking;
                        if ($booking && ! in_array($booking->status, [BookingStatus::Cancelled, BookingStatus::Expired], true)) {
                            app(BookingService::class)->cancelBooking($booking);
                        }
                    });
                }
            } catch (\Throwable $e) {
                Log::error('Payment reconciliation failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Reconciliation job: Check processing refunds with gateway (fallback for missed refund webhooks).
     */
    public function reconcileProcessingRefunds(): void
    {
        $processingRefunds = Refund::query()
            ->where('status', RefundStatus::Processing)
            ->whereNotNull('refund_id')
            ->where('updated_at', '>', now()->subHours(72))
            ->with('payment.booking.property')
            ->get();

        foreach ($processingRefunds as $refund) {
            $payment = $refund->payment;

            if (! $payment || ! $payment->booking || ! $payment->booking->property) {
                continue;
            }

            try {
                $settings = PaymentGatewaySetting::query()
                    ->where('gateway_type', $payment->gateway_type)
                    ->where('country_id', $payment->booking->property->country_id)
                    ->where('is_active', true)
                    ->first();

                if (! $settings) {
                    Log::warning('Refund reconciliation skipped: gateway settings not found', [
                        'refund_id' => $refund->id,
                        'gateway_type' => $payment->gateway_type,
                    ]);

                    continue;
                }

                $provider = $this->providerFactory->make($payment->gateway_type, $settings);
                $status = $provider->getRefundStatus($refund->refund_id, $payment->gateway_payment_id);

                if ($status['status'] === 'completed') {
                    $this->completeReconciledRefund($refund, $payment, $status['raw'] ?? []);
                } elseif ($status['status'] === 'failed') {
                    $updated = Refund::query()
                        ->whereKey($refund->getKey())
                        ->where('status', RefundStatus::Processing)
                        ->update([
                            'status' => RefundStatus::Failed,
                            'gateway_response' => $status['raw'] ?? [],
                            'processed_at' => now(),
                        ]);

                    if ($updated) {
                        app(NotificationService::class)->sendRefundNotification($refund->fresh(), RefundStatus::Failed);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Refund reconciliation failed', [
                    'refund_id' => $refund->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Mark a refund completed and create its bookkeeping payment record + notification.
     * Guarded so a refund finalised concurrently (e.g. by a webhook) is not double-processed.
     */
    private function completeReconciledRefund(Refund $refund, Payment $payment, array $raw): bool
    {
        $completed = DB::transaction(function () use ($refund, $payment, $raw) {
            $locked = Refund::query()->whereKey($refund->getKey())->lockForUpdate()->first();

            if (! $locked || $locked->status !== RefundStatus::Processing) {
                return false;
            }

            $locked->update([
                'status' => RefundStatus::Completed,
                'gateway_response' => $raw,
                'processed_at' => now(),
            ]);

            Payment::create([
                'booking_id' => $payment->booking_id,
                'user_id' => $payment->user_id,
                'gateway_type' => $payment->gateway_type,
                'gateway_payment_id' => $locked->refund_id,
                'amount' => $locked->amount,
                'currency' => $payment->currency,
                'payment_type' => PaymentType::Refund,
                'status' => PaymentTransactionStatus::Refunded,
                'paid_at' => now(),
            ]);

            return true;
        });

        if ($completed) {
            app(NotificationService::class)->sendRefundNotification($refund->fresh(), RefundStatus::Completed);
        }

        return $completed;
    }

    /**
     * Mark a pending refund as manually completed by admin.
     *
     * Used when the payment was made in cash/UPI/PayAtProperty and there is no gateway
     * to call — admin physically returns the money and confirms it here. Can also be
     * used as a last resort for gateway payments where the webhook never arrived.
     */
    public function markManualRefundComplete(Refund $refund): void
    {
        if ($refund->isCompleted()) {
            return;
        }

        DB::transaction(function () use ($refund) {
            $refund->update([
                'status' => RefundStatus::Completed,
                'processed_at' => now(),
            ]);
        });

        app(NotificationService::class)->sendRefundNotification($refund->fresh(), RefundStatus::Completed);
    }

    /**
     * Process a refund for an existing Refund record.
     */
    public function processRefund(Refund $refund): Refund
    {
        // Guard against double-processing
        if (in_array($refund->status, [RefundStatus::Processing, RefundStatus::Completed], true)) {
            return $refund;
        }

        // Load relationships and validate
        $refund->loadMissing('payment.booking.property');
        $payment = $refund->payment;

        if (! $payment || ! $payment->booking || ! $payment->booking->property) {
            throw new PaymentGatewayException('Refund is missing payment booking context.');
        }

        // Get gateway settings for this payment
        $settings = PaymentGatewaySetting::query()
            ->where('gateway_type', $payment->gateway_type)
            ->where('country_id', $payment->booking->property->country_id)
            ->where('is_active', true)
            ->first();

        if (! $settings) {
            throw new PaymentGatewayException('Gateway settings not found for refund.');
        }

        // Update refund status to processing before calling gateway
        $refund->update(['status' => RefundStatus::Processing]);

        try {
            // Get payment provider and process refund
            $provider = $this->providerFactory->make($payment->gateway_type, $settings);
            $result = $provider->refundPayment($payment->gateway_payment_id, $refund->amount, $refund->reason);

            // Map gateway's immediate status — some providers (e.g. Flutterwave) complete synchronously
            $gatewayStatus = match ($result['status'] ?? 'pending') {
                'processed', 'succeeded', 'success', 'completed' => RefundStatus::Completed,
                'failed' => RefundStatus::Failed,
                default => RefundStatus::Processing,
            };

            $refund->update([
                'refund_id' => $result['refund_id'],
                'status' => $gatewayStatus,
                'gateway_response' => $result['raw'],
                'processed_at' => now(),
            ]);

            // If gateway confirms completion immediately, create payment record and notify
            if ($gatewayStatus === RefundStatus::Completed) {
                Payment::create([
                    'booking_id' => $payment->booking_id,
                    'user_id' => $payment->user_id,
                    'gateway_type' => $payment->gateway_type,
                    'gateway_payment_id' => $result['refund_id'],
                    'amount' => $refund->amount,
                    'currency' => $payment->currency,
                    'payment_type' => PaymentType::Refund,
                    'status' => PaymentTransactionStatus::Refunded,
                    'paid_at' => now(),
                ]);

                app(NotificationService::class)->sendRefundNotification($refund->fresh(), RefundStatus::Completed);
            }

            return $refund->fresh();
        } catch (PaymentGatewayException $e) {
            // Update Refund status to "failed" on error
            $refund->update([
                'status' => RefundStatus::Failed,
                'gateway_response' => ['error' => $e->getMessage()],
                'processed_at' => now(),
            ]);

            app(NotificationService::class)->sendRefundNotification($refund->fresh(), RefundStatus::Failed);

            Log::error('Gateway refund failed', [
                'refund_id' => $refund->id,
                'payment_id' => $payment->id,
                'amount' => $refund->amount,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Process a refund for a payment (legacy method - creates refund record).
     *
     * @deprecated Use processRefund(Refund $refund) instead
     */
    public function refundPayment(Payment $payment, float $amount, ?string $reason = null): Refund
    {
        // Get gateway settings for this payment
        $settings = PaymentGatewaySetting::query()
            ->where('gateway_type', $payment->gateway_type)
            ->where('country_id', $payment->booking->property->country_id)
            ->where('is_active', true)
            ->first();

        if (! $settings) {
            throw new PaymentGatewayException('Gateway settings not found for refund.');
        }

        return DB::transaction(function () use ($payment, $amount, $reason, $settings) {
            // Create Refund record with status "pending"
            $refund = Refund::create([
                'payment_id' => $payment->id,
                'amount' => $amount,
                'status' => RefundStatus::Pending,
                'reason' => $reason,
            ]);

            try {
                // Get payment provider and process refund
                $provider = $this->providerFactory->make($payment->gateway_type, $settings);
                $result = $provider->refundPayment($payment->gateway_payment_id, $amount, $reason);

                // Update Refund record with gateway response
                $refund->update([
                    'refund_id' => $result['refund_id'],
                    'status' => RefundStatus::Processing,
                    'gateway_response' => $result['raw'],
                ]);

                return $refund->fresh();
            } catch (PaymentGatewayException $e) {
                // Update Refund status to "failed" on error
                $refund->update([
                    'status' => RefundStatus::Failed,
                    'gateway_response' => ['error' => $e->getMessage()],
                ]);

                Log::error('Gateway refund failed', [
                    'refund_id' => $refund->id,
                    'payment_id' => $payment->id,
                    'amount' => $amount,
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }
        });
    }
}
