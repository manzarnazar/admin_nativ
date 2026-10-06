<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Payments\Exceptions\SignatureVerificationException;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {}

    /**
     * Webhook endpoint for payment gateways.
     *
     * This endpoint receives webhooks from all gateways and routes them to the appropriate handler.
     * Always returns 200 OK to prevent gateway retries.
     */
    public function webhook(Request $request, string $gateway): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $headers = collect($request->headers->all())->mapWithKeys(fn ($v, $k) => [$k => $v[0] ?? ''])->toArray();

            Log::info('Webhook received', [
                'gateway' => $gateway,
                'headers' => $headers,
                'payload' => json_decode($payload, true) ?? $payload,
            ]);

            $this->paymentService->processWebhook($gateway, $payload, $headers);

            return response()->json(['status' => 'success'], 200);
        } catch (SignatureVerificationException $e) {
            Log::error('Webhook signature verification failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'signature_invalid'], 200);
        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }
    }

    /**
     * Webhook endpoint for refund status updates from payment gateways.
     *
     * This endpoint receives refund webhooks from all gateways and updates refund status.
     * Always returns 200 OK to prevent gateway retries.
     */
    public function refundWebhook(Request $request, string $gateway): JsonResponse
    {
        try {
            $payload = $request->getContent();
            $headers = collect($request->headers->all())->mapWithKeys(fn ($v, $k) => [$k => $v[0] ?? ''])->toArray();

            $this->paymentService->processRefundWebhook($gateway, $payload, $headers);

            return response()->json(['status' => 'success'], 200);
        } catch (SignatureVerificationException $e) {
            Log::error('Refund webhook signature verification failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'signature_invalid'], 200);
        } catch (\Exception $e) {
            Log::error('Refund webhook processing failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 200);
        }
    }

    /**
     * Retry a failed payment.
     *
     * Creates a new payment record for the same booking with new gateway order.
     */
    public function retry(Request $request, int $id): JsonResponse
    {
        $payment = $request->user()->payments()->findOrFail($id);

        if (! $payment->isFailed()) {
            return $this->errorResponse('Only failed payments can be retried', 400);
        }

        $newPayment = $this->paymentService->retryPayment($payment);

        return $this->successResponse([
            'payment_id' => $newPayment->id,
            'gateway_order_id' => $newPayment->gateway_order_id,
            'payment_token' => $newPayment->gateway_order_id,
            'payment_url' => $newPayment->gateway_response['url'] ?? $newPayment->gateway_response['link'] ?? null,
            'amount' => $newPayment->amount,
            'currency' => $newPayment->currency,
            'status' => $newPayment->status->value,
        ], 'Payment retry created successfully');
    }
}
