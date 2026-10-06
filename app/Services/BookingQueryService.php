<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReviewStatus;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\User;
use App\Scopes\PartnerScope;
use App\Services\Api\CurrencyConverter;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class BookingQueryService
{
    public function __construct(private CancellationPolicyService $cancellationPolicyService) {}

    /**
     * Return a paginated list of bookings for a customer with display status
     * and cancellability details.
     *
     * @param  array{status?: string, limit?: int, offset?: int}  $filters
     * @return array{items: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function getUserBookings(User $user, array $filters = []): array
    {
        $limit = (int) ($filters['limit'] ?? 10);
        $offset = (int) ($filters['offset'] ?? 0);
        $statusFilter = $filters['status'] ?? 'all';

        $query = Booking::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', BookingStatus::Expired)
            ->with([
                'property' => function ($q) {
                    $q->withAvg(['reviews' => fn ($sub) => $sub->where('status', ReviewStatus::Published)->where('is_visible', true)], 'rating')
                        ->withCount(['reviews' => fn ($sub) => $sub->where('status', ReviewStatus::Published)->where('is_visible', true)])
                        ->with(['country.refCountry', 'refCity', 'refState', 'primaryImages']);
                },
                'propertyRoom.roomType',
                'review.images',
                'payments.refunds',
                'manualRefundRequest',
            ])
            ->latest();

        $query->when($statusFilter !== 'all', function ($q) use ($statusFilter) {
            match ($statusFilter) {
                'ongoing' => $q->where('status', BookingStatus::CheckedIn),
                'completed' => $q->where('status', BookingStatus::Completed),
                'upcoming' => $q->whereIn('status', [BookingStatus::Pending, BookingStatus::Confirmed]),
                'cancelled' => $q->where('status', BookingStatus::Cancelled),
                default => $q,
            };
        });

        $paginator = $query->paginate(perPage: $limit, page: (int) floor($offset / $limit) + 1);

        return [
            'items' => $paginator->getCollection()->map(function (Booking $booking) {
                $status = $booking->status->value;

                $status = match ($booking->status->value) {
                    'pending' => 'upcoming',
                    'confirmed' => 'upcoming',
                    'checked_in' => 'ongoing',
                    'completed' => 'completed',
                    'cancelled' => 'cancelled',
                    default => $status,
                };

                $isCancellable = $booking->isCancellable();

                $refundPercentage = 0;
                $refundAmount = 0;
                $cancellationDeadline = null;
                $cancellationText = null;

                if ($isCancellable) {
                    $refundPercentage = app(CancellationPolicyService::class)->calculateRefundPercentage($booking);
                    $payment = $booking->getSuccessfulPayment();

                    if ($refundPercentage > 0 && $payment) {
                        $refundAmount = app(CancellationPolicyService::class)->calculateRefundAmount($booking, $refundPercentage);

                        $snapshot = $booking->cancellation_policy_snapshot;

                        if ($snapshot && ! empty($snapshot['rules'])) {
                            $timezone = $booking->property?->timezone ?? $booking->property?->country?->timezone ?? 'UTC';
                            $nowInPropertyTz = now()->setTimezone($timezone);
                            $effectiveToday = Carbon::today($timezone);
                            $cutoffDateTime = $effectiveToday->copy()->setTimeFromTimeString($snapshot['cancellation_cutoff_time'] ?? '23:59:59');
                            if ($nowInPropertyTz->gt($cutoffDateTime)) {
                                $effectiveToday->addDay();
                            }
                            $daysUntilCheckin = (int) $effectiveToday->copy()->startOfDay()->diffInDays(
                                $booking->check_in->copy()->startOfDay(),
                                false
                            );
                            $matchingRule = collect($snapshot['rules'])
                                ->filter(fn ($r) => (int) $r['days_before_checkin'] <= $daysUntilCheckin && (int) $r['refund_percentage'] === $refundPercentage)
                                ->sortByDesc('days_before_checkin')
                                ->first();
                            if ($matchingRule) {
                                $cancellationDeadline = $booking->check_in->copy()->subDays($matchingRule['days_before_checkin'])->toDateString();
                                $cancellationText = $refundPercentage === 100
                                    ? "Free cancel on or before {$cancellationDeadline}"
                                    : "Cancel on or before {$cancellationDeadline} for {$refundPercentage}% refund";
                            }
                        } else {
                            // Fallback for bookings created before the snapshot feature
                            $activePropertyTypeId = $booking->property?->resolvedPropertyTypeId() ?? 1;
                            $policy = CancellationPolicy::withoutGlobalScope(PartnerScope::class)
                                ->where('country_id', $booking->property?->country_id)
                                ->where('property_type_id', $activePropertyTypeId)
                                ->where('is_active', true)
                                ->first();
                            $rule = $policy?->rules()
                                ->where('days_before_checkin', '<=', now()->diffInDays($booking->check_in, false))
                                ->where('refund_percentage', $refundPercentage)
                                ->orderByDesc('days_before_checkin')
                                ->first();
                            if ($rule && $rule->days_before_checkin !== null) {
                                $cancellationDeadline = $booking->check_in->copy()->subDays($rule->days_before_checkin)->toDateString();
                                $cancellationText = $refundPercentage === 100
                                    ? "Free cancel on or before {$cancellationDeadline}"
                                    : "Cancel on or before {$cancellationDeadline} for {$refundPercentage}% refund";
                            }
                        }
                    }
                }

                return [
                    'id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'status' => $status,
                    'is_cancellable' => $isCancellable,
                    'cancellation' => [
                        'refund_percentage' => $refundPercentage,
                        'refund_amount' => round($refundAmount, 2),
                        'cancellation_deadline' => $cancellationDeadline,
                        'cancellation_text' => $cancellationText,
                    ],
                    'created_at' => $booking->created_at->toIso8601String(),
                    'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
                    'property_room_id' => $booking->property_room_id,
                    'room_type_name' => $booking->propertyRoom?->roomType?->name,
                    'property' => [
                        'name' => $booking->property?->name,
                        'slug' => $booking->property?->slug,
                        'rating' => $booking->property?->reviews_avg_rating ? (float) number_format($booking->property->reviews_avg_rating, 1) : 0,
                        'review_count' => $booking->property?->reviews_count ?? 0,
                        'address' => $booking->property?->street_address.', '.$booking->property?->refCity?->name.', '.$booking->property?->zip_code,
                        'image' => ($booking->property?->primaryImages->firstWhere('media_type', 'image') ?? $booking->property?->primaryImages->first())?->image_path ? asset('storage/'.($booking->property->primaryImages->firstWhere('media_type', 'image') ?? $booking->property->primaryImages->first())->image_path) : null,
                    ],
                    'check_in' => $booking->check_in->toDateString(),
                    'check_out' => $booking->check_out->toDateString(),
                    'total_nights' => $booking->total_nights,
                    'booked_rooms' => $booking->booked_rooms,
                    'check_in_time' => $booking->property?->check_in_time,
                    'check_out_time' => $booking->property?->check_out_time,
                    'total_amount' => (float) $booking->total_amount,
                    'currency_symbol' => $booking->currency_symbol,
                    'refund' => $booking->payments->flatMap->refunds->first() ? [
                        'id' => $booking->payments->flatMap->refunds->first()->id,
                        'amount' => (float) $booking->payments->flatMap->refunds->first()->amount,
                        'status' => $booking->payments->flatMap->refunds->first()->status->value,
                        'refund_id' => $booking->payments->flatMap->refunds->first()->refund_id,
                        'refund_percentage' => (float) $booking->payments->flatMap->refunds->first()->refund_percentage,
                        'processed_at' => $booking->payments->flatMap->refunds->first()->processed_at?->toIso8601String(),
                        'is_manual_request_submitted' => $booking->manualRefundRequest !== null,
                    ] : null,
                    'review' => $booking->review ? [
                        'id' => $booking->review->id,
                        'rating' => (float) $booking->review->rating,
                        'review' => $booking->review->review,
                        'status' => $booking->review->status,
                        'is_edited' => $booking->review->is_edited,
                        'edited_at' => $booking->review->edited_at?->toIso8601String(),
                        'images' => $booking->review->images->map(fn ($img) => [
                            'id' => $img->id,
                            'url' => asset('storage/'.$img->image_path),
                        ])->values()->toArray(),
                    ] : null,
                ];
            })->values()->toArray(),
            'pagination' => [
                'total' => $paginator->total(),
                'limit' => $paginator->perPage(),
                'offset' => ($paginator->currentPage() - 1) * $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ];
    }

    /**
     * Return full detail of a single booking for a customer.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function getBookingDetail(User $user, string $bookingNumber): array
    {
        /** @var Booking|null $booking */
        $booking = Booking::query()
            ->where('user_id', $user->id)
            ->where('booking_number', $bookingNumber)
            ->where('status', '!=', BookingStatus::Expired)
            ->with([
                'property' => function ($q) {
                    $q->withAvg(['reviews' => fn ($sub) => $sub->where('status', ReviewStatus::Published)->where('is_visible', true)], 'rating')
                        ->withCount(['reviews' => fn ($sub) => $sub->where('status', ReviewStatus::Published)->where('is_visible', true)])
                        ->with(['country.refCountry', 'primaryImages', 'refCity', 'refState']);
                },
                'propertyRoom.roomType',
                'review.images',
                'payments.refunds',
                'manualRefundRequest',
            ])
            ->first();

        if (! $booking) {
            throw ValidationException::withMessages([
                'booking_number' => 'Booking not found.',
            ]);
        }

        $currencyCode = $booking->currency_code ?? $booking->property?->country?->currency_code ?? 'INR';
        $currencySymbol = $booking->currency_symbol ?? $booking->property?->country?->currency_symbol ?? '₹';

        $pricingData = [
            'price_per_night' => (float) $booking->price_per_night,
            'subtotal' => (float) $booking->base_amount,
            'tax_amount' => (float) $booking->tax_amount,
            'discount_amount' => (float) $booking->discount_amount,
            'total_amount' => (float) $booking->total_amount,
            'currency_code' => $currencyCode,
            'currency_symbol' => $currencySymbol,
            'tax_details' => $booking->tax_details,
        ];

        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->base_amount, $currencyCode, 'subtotal');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->tax_amount, $currencyCode, 'tax_amount');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->discount_amount, $currencyCode, 'discount_amount');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->total_amount, $currencyCode, 'total_amount');

        $advancePercentage = $booking->property?->advance_percentage ?? 0;
        $amountPaid = $booking->payment_status === PaymentStatus::Paid
            ? (float) $booking->total_amount
            : ($advancePercentage > 0 ? round((float) $booking->total_amount * $advancePercentage / 100, 2) : 0.0);
        $remainingAmount = round((float) $booking->total_amount - $amountPaid, 2);

        $isCancellable = $booking->isCancellable();

        $status = match ($booking->status->value) {
            'pending' => 'upcoming',
            'confirmed' => 'upcoming',
            'checked_in' => 'ongoing',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => $booking->status->value,
        };

        return [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $status,
            'status_label' => $booking->status->label(),
            'status_color' => $booking->status->color(),
            'payment_status' => $booking->payment_status->value,
            'payment_method' => $booking->payment_method?->value,
            'booking_source' => $booking->booking_source?->value,
            'created_at' => $booking->created_at->toIso8601String(),
            'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $booking->cancellation_reason,
            'is_cancellable' => $isCancellable,
            'refund' => $booking->payments->flatMap->refunds->first() ? (function () use ($booking) {
                $refund = $booking->payments->flatMap->refunds->first();
                $isManual = $booking->manualRefundRequest !== null;

                return [
                    'id' => $refund->id,
                    'amount' => (float) $refund->amount,
                    'status' => $refund->status->value,
                    'refund_id' => $refund->refund_id,
                    'refund_percentage' => (float) $refund->refund_percentage,
                    'processed_at' => $refund->processed_at?->toIso8601String(),
                    'is_manual_request_submitted' => $isManual,
                    'refund_method' => $isManual ? 'manual_bank_transfer' : 'online',
                    'transaction_id' => $isManual
                        ? $booking->manualRefundRequest->transfer_reference_id
                        : $refund->refund_id,
                ];
            })() : null,
            'property' => [
                'name' => $booking->property?->name,
                'slug' => $booking->property?->slug,
                'street_address' => $booking->property?->street_address,
                'city' => $booking->property?->refCity?->name,
                'state' => $booking->property?->refState?->name,
                'zip_code' => $booking->property?->zip_code,
                'latitude' => $booking->property?->latitude ? (float) $booking->property->latitude : null,
                'longitude' => $booking->property?->longitude ? (float) $booking->property->longitude : null,
                'place_id' => $booking->property?->place_id,
                'rating' => $booking->property?->reviews_avg_rating ? (float) number_format($booking->property->reviews_avg_rating, 1) : 0,
                'review_count' => $booking->property?->reviews_count ?? 0,
                'check_in_time' => $booking->property?->check_in_time,
                'check_out_time' => $booking->property?->check_out_time,
                'pay_at_property' => (bool) $booking->property?->pay_at_property,
                'advance_percentage' => (float) $advancePercentage,
                'primary_image' => ($booking->property?->primaryImages->firstWhere('media_type', 'image') ?? $booking->property?->primaryImages->first())?->image_path
                    ? asset('storage/'.($booking->property->primaryImages->firstWhere('media_type', 'image') ?? $booking->property->primaryImages->first())->image_path)
                    : null,
            ],
            'property_room_id' => $booking->property_room_id,
            'room_type_name' => $booking->propertyRoom?->roomType?->name,
            'check_in' => $booking->check_in->toDateString(),
            'check_out' => $booking->check_out->toDateString(),
            'total_nights' => $booking->total_nights,
            'booked_rooms' => $booking->booked_rooms,
            'adults' => $booking->adults,
            'children' => $booking->children,
            'has_pets' => $booking->has_pets,
            'guest_name' => $booking->guest_name,
            'guest_email' => $booking->guest_email,
            'guest_phone' => $booking->guest_phone,
            'guest_dial_code' => $booking->guest_dial_code,
            'pricing' => array_merge($pricingData, [
                'amount_paid' => $amountPaid,
                'remaining_amount' => $remainingAmount,
            ]),
            'review' => $booking->review ? [
                'id' => $booking->review->id,
                'rating' => (float) $booking->review->rating,
                'review' => $booking->review->review,
                'status' => $booking->review->status,
                'is_edited' => $booking->review->is_edited,
                'edited_at' => $booking->review->edited_at?->toIso8601String(),
                'images' => $booking->review->images->map(fn ($img) => [
                    'id' => $img->id,
                    'url' => asset('storage/'.$img->image_path),
                ])->values()->toArray(),
            ] : null,
            'cancellation_policy' => $booking->property
                ? ($booking->cancellation_policy_snapshot['display'] ?? $this->cancellationPolicyService->getSummary($booking->property, $booking->check_in->toDateString()))
                : ['free_cancellation_until' => null, 'cancellation_cutoff_time' => null, 'rules' => []],
        ];
    }
}
