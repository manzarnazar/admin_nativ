<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookingStatus;
use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ManualRefundRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ManualRefundController extends Controller
{
    /**
     * Submit a manual refund request.
     *
     * Customer submits bank details for a manual bank transfer refund.
     * Only one request is allowed per booking (enforced by unique constraint on booking_id).
     */
    public function store(Request $request, string $bookingNumber): JsonResponse
    {
        $validated = $request->validate([
            'account_holder_name' => ['required', 'string', 'max:255'],
            'bank_name' => ['required', 'string', 'max:255'],
            'account_number' => ['required', 'string', 'max:255'],
            'ifsc_swift_code' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:500'],
        ]);

        $user = $request->user();

        $booking = Booking::query()
            ->where('booking_number', $bookingNumber)
            ->where('user_id', $user->id)
            ->with('payments.refunds')
            ->first();

        if (! $booking) {
            throw ValidationException::withMessages([
                'booking_number' => 'Booking not found or does not belong to you.',
            ]);
        }

        if ($booking->status !== BookingStatus::Cancelled) {
            throw ValidationException::withMessages([
                'booking_number' => 'Manual refund requests can only be submitted for cancelled bookings.',
            ]);
        }

        $alreadyExists = ManualRefundRequest::where('booking_id', $booking->id)->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'booking_number' => 'A manual refund request has already been submitted for this booking.',
            ]);
        }

        $failedRefund = $booking->payments->flatMap->refunds
            ->first(fn ($r) => $r->status === RefundStatus::Failed);

        $amount = $failedRefund
            ? (float) $failedRefund->amount
            : (float) $booking->total_amount;

        $refundRequest = ManualRefundRequest::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'account_holder_name' => $validated['account_holder_name'],
            'bank_name' => $validated['bank_name'],
            'account_number' => $validated['account_number'],
            'ifsc_swift_code' => $validated['ifsc_swift_code'],
            'amount' => $amount,
            'message' => $validated['message'],
            'status' => 'pending_review',
        ]);

        return $this->successResponse([
            'id' => $refundRequest->id,
            'refund_number' => $refundRequest->refund_number,
            'booking_number' => $booking->booking_number,
            'account_holder_name' => $refundRequest->account_holder_name,
            'bank_name' => $refundRequest->bank_name,
            'account_number' => $refundRequest->account_number,
            'ifsc_swift_code' => $refundRequest->ifsc_swift_code,
            'amount' => (float) $refundRequest->amount,
            'message' => $refundRequest->message,
            'status' => $refundRequest->status->value,
            'transaction_id' => $refundRequest->transaction_id,
            'submitted_at' => $refundRequest->created_at->toIso8601String(),
        ], 'Manual refund request submitted successfully.', 201);
    }
}
