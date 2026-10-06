<?php

namespace App\Http\Controllers\Api;

use App\Enums\CancellationInitiator;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Booking\ConfirmBookingRequest;
use App\Http\Requests\Api\Booking\LockBookingRequest;
use App\Http\Requests\Api\Booking\QuoteBookingRequest;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Scopes\PartnerScope;
use App\Services\BookingQueryService;
use App\Services\BookingService;
use App\Services\CancellationPolicyService;
use App\Services\InvoiceService;
use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private BookingQueryService $bookingQueryService,
    ) {}

    /**
     * List user bookings.
     *
     * Returns a list of the authenticated user's bookings with basic info.
     * Filters: status (all, ongoing, completed, upcoming, cancelled).
     */
    public function index(Request $request): JsonResponse
    {
        // Log::info('booking ', ['request' => $request->all(), 'token' => $request->bearerToken()]);
        $request->validate([
            'status' => ['nullable', 'string', 'in:all,ongoing,completed,upcoming,cancelled'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        $result = $this->bookingQueryService->getUserBookings($request->user(), $request->all());

        return $this->successResponse($result, 'Bookings fetched successfully');
    }

    /**
     * Generate a booking quote.
     *
     * Calculates pricing, taxes, and availability for a stay.
     */
    public function quote(QuoteBookingRequest $request): JsonResponse
    {
        $result = $this->bookingService->generateQuote($request->user(), $request->validated());

        return $this->successResponse($result, 'Booking quote generated successfully');
    }

    /**
     * Lock inventory for a booking.
     *
     * Places a 10-minute hold on the selected room type for the given dates.
     */
    #[BodyParameter('property_room_id', description: 'Room ID', type: 'int', required: true, example: 1)]
    #[BodyParameter('check_in', description: 'Check-in date', type: 'string', format: 'date', required: true, example: '2026-08-27')]
    #[BodyParameter('check_out', description: 'Check-out date', type: 'string', format: 'date', required: true, example: '2026-08-29')]
    #[BodyParameter('rooms', description: 'Number of rooms', type: 'int', required: true, example: 1)]
    public function lock(LockBookingRequest $request): JsonResponse
    {
        $result = $this->bookingService->createInventoryLock($request->user(), $request->validated());

        return $this->successResponse($result, 'Inventory locked successfully');
    }

    /**
     * Confirm a booking from an active lock.
     *
     * Converts an inventory lock into a finalized booking record with guest snapshots.
     *
     * Payment handling:
     * - When the server-computed total is greater than 0, `payment_method` is required and must
     *   be `pay_at_property` (use the payment-initiation endpoint for `pay_online`); the property
     *   must allow pay-at-property. The booking is created with `payment_status: unpaid`.
     * - When the server-computed total is 0 (e.g. a fully-discounted / coupon booking),
     *   `payment_method` is optional and ignored. The booking is confirmed with
     *   `payment_status: paid` and `payment_method: null` — no payment is collected.
     */
    public function confirm(ConfirmBookingRequest $request): JsonResponse
    {
        $result = $this->bookingService->confirmBookingFromLock($request->user(), $request->validated());

        return $this->successResponse($result, 'Booking confirmed successfully', 201);
    }

    /**
     * Create a booking with gateway payment initiation.
     *
     * Creates a booking in PENDING_PAYMENT status and initiates payment via gateway.
     * The booking will be confirmed when webhook payment succeeds.
     */
    #[BodyParameter('lock_id', description: 'Lock ID', type: 'int', required: true, example: 0)]
    #[BodyParameter('gateway_type', description: 'Gateway type (razorpay, stripe, flutterwave)', type: 'string', required: true, example: 'razorpay')]
    #[BodyParameter('payment_type', description: 'Payment type: full (100% payment) or partial (advance payment based on property advance_percentage)', type: 'string', required: true, example: 'full')]
    #[BodyParameter('adults', description: 'Number of adults', type: 'int', required: true, example: 1)]
    #[BodyParameter('children', description: 'Number of children', type: 'int', required: true, example: 0)]
    #[BodyParameter('has_pets', description: 'Has pets', type: 'boolean', required: true, example: true)]
    #[BodyParameter('guest_name', description: 'Guest name', type: 'string', required: true, example: 'string')]
    #[BodyParameter('guest_email', description: 'Guest email', type: 'string', format: 'email', required: true, example: 'guest@example.com')]
    #[BodyParameter('guest_phone', description: 'Guest phone', type: 'string', required: true, example: '9898789574')]
    #[BodyParameter('guest_dial_code', description: 'Guest dial code', type: 'string', required: false, example: '+91')]
    #[BodyParameter('coupon_code', description: 'Coupon code', type: 'string', required: false, example: null)]
    #[BodyParameter('promo_code', description: 'Promo code', type: 'string', required: false, example: null)]
    public function createWithPayment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lock_id' => ['required', 'exists:inventory_locks,id'],
            'gateway_type' => ['required', 'in:razorpay,stripe,flutterwave'],
            'payment_type' => ['required', 'in:full,partial'],
            'adults' => ['required', 'integer', 'min:1'],
            'children' => ['required', 'integer', 'min:0'],
            'has_pets' => ['required', 'boolean'],
            'guest_name' => ['required', 'string', 'max:255'],
            'guest_email' => ['required', 'email'],
            'guest_phone' => ['required', 'string', 'max:20', 'regex:/^[0-9]{7,15}$/'],
            'guest_dial_code' => ['nullable', 'string'],
            'coupon_code' => ['nullable', 'string'],
        ]);

        // API always uses pay_online for web/mobile customers
        $validated['payment_method'] = 'pay_online';

        $result = $this->bookingService->createBookingWithPayment($request->user(), $validated);

        if (isset($result['payment_error'])) {
            return $this->errorResponse(
                'Booking created but payment initiation failed. Please retry payment.',
                422,
                ['booking' => $result['booking'], 'gateway_error' => $result['payment_error']]
            );
        }

        return $this->successResponse($result, 'Booking created with payment initiated', 201);
    }

    /**
     * Retry payment for a failed booking payment.
     *
     * Creates a new payment attempt for an existing booking in PENDING_PAYMENT status.
     * Only bookings with failed payments can be retried.
     */
    #[BodyParameter('gateway_type', description: 'Payment gateway (razorpay, stripe, flutterwave)', type: 'string', required: false, example: 'razorpay')]
    #[BodyParameter('payment_type', description: 'Payment type (full, partial)', type: 'string', required: false, example: 'full')]
    public function retryPayment(Request $request, string $bookingNumber): JsonResponse
    {
        $validated = $request->validate([
            'gateway_type' => ['nullable', 'in:razorpay,stripe,flutterwave'],
            'payment_type' => ['nullable', 'in:full,partial'],
        ]);

        $result = $this->bookingService->retryBookingPayment(
            $request->user(),
            $bookingNumber,
            $validated
        );

        return $this->successResponse($result, 'Payment retry initiated', 201);
    }

    /**
     * Get full detail of a single booking.
     *
     * Returns complete booking info including property details, pricing breakdown,
     * guest snapshot, cancellation eligibility, and advance/remaining payment amounts.
     */
    public function show(Request $request, string $bookingNumber): JsonResponse
    {
        $result = $this->bookingQueryService->getBookingDetail($request->user(), $bookingNumber);

        return $this->successResponse($result, 'Booking fetched successfully');
    }

    /**
     * Download invoice for a booking.
     *
     * Returns a PDF invoice for the authenticated user's booking.
     */
    public function downloadInvoice(Request $request, string $bookingNumber): Response
    {
        $booking = Booking::where('booking_number', $bookingNumber)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        return app(InvoiceService::class)->download($booking);
    }

    /**
     * Preview cancellation details for a booking.
     *
     * Returns refund calculation without processing the cancellation.
     */
    public function cancelPreview(Request $request, string $bookingNumber): JsonResponse
    {
        $booking = Booking::where('booking_number', $bookingNumber)
            ->where('user_id', $request->user()->id)
            ->with('property')
            ->firstOrFail();

        // Determine if booking can be cancelled
        $canCancel = true;
        $reason = null;

        if (in_array($booking->status->value, ['cancelled', 'checked_in', 'completed'])) {
            $canCancel = false;
            $reason = 'Booking cannot be cancelled in current status';
        }

        // Calculate refund percentage using CancellationPolicyService with timezone handling
        $refundPercentage = 0;
        $refundAmount = 0;
        $cancellationFee = 0;
        $appliedRule = null;

        if ($canCancel) {
            $refundPercentage = app(CancellationPolicyService::class)->calculateRefundPercentage($booking);
            $payment = $booking->getSuccessfulPayment();

            if ($refundPercentage > 0 && $payment) {
                $refundAmount = app(CancellationPolicyService::class)->calculateRefundAmount($booking, $refundPercentage);
                $cancellationFee = $payment->amount - $refundAmount;

                // Get applied rule for display
                $policy = CancellationPolicy::withoutGlobalScope(PartnerScope::class)
                    ->where('country_id', $booking->property?->country_id)
                    ->where('is_active', true)
                    ->orderByRaw('CASE WHEN property_type_id = ? THEN 0 ELSE 1 END', [$booking->property?->property_type_id])
                    ->first();
                $rule = $policy?->rules()
                    ->where('days_before_checkin', '<=', now()->diffInDays($booking->check_in, false))
                    ->orderByDesc('days_before_checkin')
                    ->first();

                if ($rule) {
                    $appliedRule = [
                        'days_before_checkin' => $rule->days_before_checkin,
                        'refund_percentage' => $rule->refund_percentage,
                    ];
                }
            }
        }

        // Calculate refund deadline date
        $refundDeadline = null;
        if ($appliedRule && $appliedRule['days_before_checkin'] !== null) {
            $refundDeadline = $booking->check_in->copy()->subDays($appliedRule['days_before_checkin'])->toDateString();
        }

        return $this->successResponse([
            'booking_number' => $booking->booking_number,
            'can_cancel' => $canCancel,
            'reason' => $reason,
            'refund_amount' => $refundAmount,
            'refund_percentage' => $refundPercentage,
            'cancellation_fee' => $cancellationFee,
            'is_free_cancellation' => $refundPercentage === 100,
            'refund_deadline' => $refundDeadline,
            'applied_rule' => $appliedRule,
        ], 'Refund preview calculated');
    }

    /**
     * Cancel a booking with refund processing.
     *
     * Cancels the booking and processes refund based on cancellation policy.
     */
    public function cancel(Request $request, string $bookingNumber): JsonResponse
    {
        $booking = Booking::where('booking_number', $bookingNumber)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $result = $this->bookingService->cancelBookingWithRefund($booking, null, CancellationInitiator::Customer);

        return $this->successResponse($result, 'Booking cancelled successfully');
    }
}
