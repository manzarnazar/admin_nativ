<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    /**
     * Get the transaction history for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = $request->query('limit', 10);
        $offset = $request->query('offset', 0);

        $query = Payment::query()
            ->where('user_id', $request->user()->id)
            ->with([
                'booking:id,booking_number,property_id,status,total_amount,currency_code,currency_symbol,cancelled_at',
                'booking.property:id,name,slug',
            ])
            ->latest();

        $paginator = $query->paginate($limit, ['*'], 'page', (int) ($offset / $limit) + 1);

        $items = $paginator->getCollection()->map(function ($payment) {
            $description = match (true) {
                $payment->payment_type === PaymentType::Refund => 'Payment Refunded',
                $payment->status === PaymentTransactionStatus::Cancelled => 'Cancelled Booking',
                $payment->payment_type === PaymentType::Partial => 'Advance Booking Payment',
                $payment->payment_type === PaymentType::Full => 'Booking Payment',
                default => $payment->status?->label() ?? 'Payment',
            };

            $type = $payment->payment_type === PaymentType::Refund ? 'credit' : 'debit';

            return [
                'id' => $payment->id,
                'booking_number' => $payment->booking->booking_number ?? null,
                'property_name' => $payment->booking->property->name ?? null,
                'gateway' => $payment->gateway_type?->label() ?? $payment->gateway_type,
                'transaction_id' => $payment->gateway_payment_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'currency_symbol' => $payment->booking?->currency_symbol,
                'status' => $payment->status === PaymentTransactionStatus::Flagged
                    ? PaymentTransactionStatus::Failed->value
                    : ($payment->status?->value ?? $payment->status),
                'payment_type' => $payment->payment_type?->value ?? $payment->payment_type,
                'description' => $description,
                'type' => $type,
                'paid_at' => $payment->paid_at?->toIso8601String(),
                'created_at' => $payment->created_at->toIso8601String(),
            ];
        });

        return $this->paginatedResponse($paginator, $items->toArray(), 'Transaction history fetched successfully', [], $offset);
    }
}
