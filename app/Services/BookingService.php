<?php

namespace App\Services;

use App\Actions\CalculatePricingAction;
use App\Actions\FindOrCreateCustomerAction;
use App\Actions\SendBookingNotificationsAction;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\CancellationInitiator;
use App\Enums\InventoryLockStatus;
use App\Enums\NotificationCategory;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Enums\PropertyStatus;
use App\Enums\RefundStatus;
use App\Enums\UserRole;
use App\Enums\WalletTransactionReferenceType;
use App\Mail\BookingConfirmationMailable;
use App\Models\Booking;
use App\Models\InventoryLock;
use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyWallet;
use App\Models\Refund;
use App\Models\Tax;
use App\Models\User;
use App\Services\Api\CouponService;
use App\Services\Api\CurrencyConverter;
use App\Services\Api\ReferralService;
use App\Services\Payments\Exceptions\PaymentGatewayException;
use App\Services\Payments\PaymentProviderFactory;
use App\Services\Payments\PaymentService;
use App\Support\SystemMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class BookingService
{
    public function __construct(
        private CalculatePricingAction $calculatePricingAction,
        private FindOrCreateCustomerAction $findOrCreateCustomerAction,
        private SendBookingNotificationsAction $sendBookingNotificationsAction,
        private BookingInventoryService $inventoryService,
    ) {}

    /**
     * Generate a customer-facing booking quote.
     *
     * @param  array{property_room_id: int, check_in: string, check_out: string, adults?: int, children?: int, rooms?: int, has_pets?: bool, coupon_code?: ?string}  $data
     * @return array<string, mixed>
     */
    public function generateQuote(User $user, array $data): array
    {

        $propertyRoom = $this->getBookablePropertyRoom($data['property_room_id']);

        $property = $propertyRoom->property;

        $checkIn = Carbon::parse($data['check_in'])->toDateString();
        $checkOut = Carbon::parse($data['check_out'])->toDateString();
        $requestedRooms = (int) ($data['rooms'] ?? 1);
        $adults = (int) ($data['adults'] ?? 1);
        $children = (int) ($data['children'] ?? 0);
        $hasPets = (bool) ($data['has_pets'] ?? false);

        $nights = $this->calculatePricingAction->nights($checkIn, $checkOut);

        // Check for existing pending booking FIRST (before availability check)
        $pendingBooking = Booking::query()
            ->where('user_id', $user->id)
            ->where('property_room_id', $propertyRoom->id)
            ->where('check_in', $checkIn)
            ->where('check_out', $checkOut)
            ->where('status', BookingStatus::PendingPayment)
            ->latest()
            ->first();

        // Initialize availableRooms
        $availableRooms = 0;

        // Only check availability if there's no pending booking
        if (! $pendingBooking) {
            $availableRooms = $this->getAvailableRooms($propertyRoom, $checkIn, $checkOut);

            // Check Guest Capacity
            $this->assertCapacityAllowed($propertyRoom, $requestedRooms, $adults, $children);

            if ($availableRooms < $requestedRooms) {
                $message = $availableRooms === 0
                    ? 'No rooms are available for the selected dates.'
                    : "Only {$availableRooms} room(s) are available for the selected dates.";

                throw ValidationException::withMessages([
                    'rooms' => $message,
                ]);
            }
        } else {
            // For pending booking, assume requested rooms are available
            $availableRooms = $requestedRooms;
        }

        $pricing = $this->calculatePricingAction->handle($propertyRoom, $nights, $requestedRooms, $property->country_id, $property->property_type_id);

        $couponData = null;
        $promoData = null;
        $discountAmount = 0.0;
        $promoId = null;
        $couponId = null;

        if (! empty($data['coupon_code'])) {
            // First try to validate as promo code (admin-created)
            try {
                $promo = app(PromoCodeService::class)->validatePromoCode(
                    $data['coupon_code'],
                    $user,
                    (float) $pricing['total_amount'],
                    $property->country_id,
                    $property->ref_city_id,
                );

                $discountAmount = app(PromoCodeService::class)->calculateDiscount($promo, (float) $pricing['base_amount']);
                $promoId = $promo->id;

                $promoData = [
                    'code' => $promo->code,
                    'type' => $promo->discount_type->value,
                    'value' => $promo->discount_value,
                ];
            } catch (\Exception $promoException) {
                // If promo code validation fails, try as coupon (user voucher)
                try {
                    $coupon = app(CouponService::class)->validateCoupon(
                        $data['coupon_code'],
                        $user,
                        (float) $pricing['total_amount'],
                    );

                    $discountAmount = app(CouponService::class)->calculateDiscount($coupon, (float) $pricing['base_amount']);
                    $couponId = $coupon->id;

                    $couponData = [
                        'code' => $coupon->code,
                        'type' => $coupon->type->value,
                        'value' => $coupon->value,
                    ];
                } catch (\Exception $couponException) {
                    throw ValidationException::withMessages([
                        'coupon_code' => 'Invalid coupon or promo code.',
                    ]);
                }
            }
        } else {
            // If no coupon code passed, check for auto-apply promos (unless skip_auto_promo is true)
            $skipAutoPromo = (bool) ($data['skip_auto_promo'] ?? false);

            if (! $skipAutoPromo) {
                $appliedPromo = app(PromoCodeService::class)->findAutoApplyPromo(
                    $user,
                    (float) $pricing['total_amount'],
                    $property->country_id,
                    $property->ref_city_id,
                );

                if ($appliedPromo) {
                    $discountAmount = app(PromoCodeService::class)->calculateDiscount($appliedPromo, (float) $pricing['base_amount']);
                    $promoId = $appliedPromo->id;

                    $promoData = [
                        'code' => $appliedPromo->code,
                        'type' => $appliedPromo->discount_type->value,
                        'value' => $appliedPromo->discount_value,
                    ];
                }
            }
        }

        // Recompute with the resolved discount so tax is calculated on the
        // discounted room amount, not the original — base_amount is unaffected.
        $pricing = $this->calculatePricingAction->handle($propertyRoom, $nights, $requestedRooms, $property->country_id, $property->property_type_id, $discountAmount);
        $finalTotal = (float) $pricing['total_amount'];
        $currencyCode = (string) ($property->country?->currency_code ?? 'INR');
        $currencySymbol = (string) ($property->country?->currency_symbol ?? '₹');

        $advancePercentage = (float) ($property->advance_percentage ?? 0);
        $advanceAmount = round($finalTotal * ($advancePercentage / 100), 2);

        $pricingData = [
            'subtotal' => (float) $pricing['base_amount'],
            'tax_amount' => (float) $pricing['tax_amount'],
            'discount_amount' => $discountAmount,
            'total_amount' => $finalTotal,
            'currency_code' => $currencyCode,
            'currency_symbol' => $currencySymbol,
            'tax_details' => $pricing['tax_details'],
        ];

        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $pricing['base_amount'], $currencyCode, 'subtotal');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $pricing['tax_amount'], $currencyCode, 'tax_amount');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, $discountAmount, $currencyCode, 'discount_amount');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, $finalTotal, $currencyCode, 'total_amount');

        // Get available payment gateways for this property's country
        $availableGateways = PaymentGatewaySetting::query()
            ->where('country_id', $property->country_id)
            ->where('is_active', true)
            ->pluck('gateway_type')
            ->unique()
            ->values()
            ->toArray();

        // Commission is charged on the full undiscounted base_amount — Pay at Property
        // and partial payment both collect less than that online for a discounted
        // booking, so neither is offered once a discount has resolved. Single-mode/
        // no-partner properties have no wallet-crediting concept at all (see
        // checkIn()'s own gate), so this only ever restricts a multi-mode,
        // partner-owned property — surfaced here so the app can hide these options
        // before the guest picks one, rather than only rejecting it at confirm time.
        // Toggle: config('app.require_full_payment_for_discounted_bookings').
        $discountRestrictsPaymentOptions = config('app.require_full_payment_for_discounted_bookings')
            && $discountAmount > 0 && SystemMode::isMulti() && $property->partner_id;

        // Determine available payment methods based on property settings
        $availableMethods = [];
        if ($property->pay_at_property && ! $discountRestrictsPaymentOptions) {
            $availableMethods[] = PaymentMethod::PayAtProperty->value;
        }
        if (! empty($availableGateways)) {
            $availableMethods[] = PaymentMethod::PayOnline->value;
        }

        $partialPaymentAvailable = ! $discountRestrictsPaymentOptions;

        $paymentInfo = [
            'has_pending_booking' => false,
            'booking_number' => null,
            'can_retry' => false,
            'reason' => null,
        ];

        if ($pendingBooking) {
            $retryMinutesLimit = config('app.inventory_lock_expiry_minutes', 10);
            $canRetry = true;
            $reason = null;

            if ($pendingBooking->created_at->lt(now()->subMinutes($retryMinutesLimit))) {
                $canRetry = false;
                $reason = 'Booking expired. Please start a new booking.';
            } elseif ($pendingBooking->property->status !== PropertyStatus::Active) {
                $canRetry = false;
                $reason = 'Property is no longer available.';
            }

            $paymentInfo = [
                'has_pending_booking' => true,
                'booking_number' => $pendingBooking->booking_number,
                'can_retry' => $canRetry,
                'reason' => $reason,
            ];
        }

        return [
            'property' => [
                'id' => $property->id,
                'name' => $property->name,
                'slug' => $property->slug,
                'city' => $property->refCity?->name,
                'state' => $property->refState?->name,
                'pay_at_property' => (bool) $property->pay_at_property,
                'advance_percentage' => (float) ($property->advance_percentage ?? 0),
            ],
            'room' => CurrencyConverter::addConvertedPrice(
                [
                    'property_room_id' => $propertyRoom->id,
                    'room_type_id' => $propertyRoom->room_type_id,
                    'name' => $propertyRoom->roomType?->name,
                    'bed_type' => $propertyRoom->roomType?->bed_type,
                    'max_guests' => $propertyRoom->roomType?->max_guests,
                    'room_size' => $propertyRoom->room_size,
                    'base_price_per_night' => (float) $propertyRoom->base_price_per_night,
                    'currency_code' => $currencyCode,
                    'currency_symbol' => $currencySymbol,
                ],
                (float) $propertyRoom->base_price_per_night,
                $currencyCode,
                'base_price_per_night'
            ),
            'stay' => [
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'nights' => $nights,
                'adults' => $adults,
                'children' => $children,
                'rooms' => $requestedRooms,
                'has_pets' => $hasPets,
            ],
            'availability' => [
                'available_rooms' => $availableRooms,
                'requested_rooms' => $requestedRooms,
                // 'is_available' => true,
                'is_available' => $availableRooms >= $requestedRooms,
            ],
            'coupon' => $couponData,
            'promo_code' => $promoData,
            'pricing' => $pricingData,
            'payment' => array_merge(
                CurrencyConverter::addConvertedPrice(
                    [
                        'available_methods' => $availableMethods,
                        'partial_payment_available' => $partialPaymentAvailable,
                        'payment_status_on_confirm' => PaymentStatus::Unpaid->value,
                        'advance_amount' => $advanceAmount,
                        'currency_code' => $currencyCode,
                        'currency_symbol' => $currencySymbol,
                        'available_gateways' => $availableGateways,
                    ],
                    $advanceAmount,
                    $currencyCode,
                    'advance_amount'
                ),
                $paymentInfo
            ),
            'cancellation_policy' => app(CancellationPolicyService::class)->getSummary($property, $checkIn),
        ];
    }

    /**
     * Create or reuse a temporary inventory lock for checkout.
     *
     * @param  array{property_room_id: int, check_in: string, check_out: string, rooms: int}  $data
     * @return array<string, mixed>
     */
    public function createInventoryLock(User $user, array $data): array
    {
        $propertyRoom = $this->getBookablePropertyRoom($data['property_room_id']);
        $property = $propertyRoom->property;

        $checkIn = Carbon::parse($data['check_in'])->toDateString();
        $checkOut = Carbon::parse($data['check_out'])->toDateString();
        $quantity = (int) $data['rooms'];
        $lockExpiryMinutes = (int) config('app.inventory_lock_expiry_minutes', 10);
        $expiresAt = now()->addMinutes($lockExpiryMinutes);

        $platform = $data['platform'] ?? null;

        $lock = DB::transaction(function () use ($user, $property, $propertyRoom, $checkIn, $checkOut, $quantity, $expiresAt, $lockExpiryMinutes, $platform) {
            $activeLock = InventoryLock::query()
                ->where('user_id', $user->id)
                ->where('property_room_id', $propertyRoom->id)
                ->whereDate('check_in', $checkIn)
                ->whereDate('check_out', $checkOut)
                ->where('quantity', $quantity)
                ->where('status', InventoryLockStatus::Active->value)
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            if ($activeLock) {
                // Refresh platform too — this request may come from a different client
                // than whichever one originally created the still-active lock.
                $activeLock->update(['expires_at' => $expiresAt, 'platform' => $platform ?? $activeLock->platform]);

                return $activeLock->fresh();
            }

            // Resume a converted lock whose pending-payment booking is still within the retry window.
            // Prevents creating duplicate PendingPayment bookings when the user comes back from the
            // payment gateway without paying and clicks "Book Now" again.
            $resumableLock = InventoryLock::query()
                ->with('booking')
                ->where('user_id', $user->id)
                ->where('property_room_id', $propertyRoom->id)
                ->whereDate('check_in', $checkIn)
                ->whereDate('check_out', $checkOut)
                ->where('quantity', $quantity)
                ->where('status', InventoryLockStatus::Converted->value)
                ->whereHas(
                    'booking',
                    fn ($q) => $q
                        ->where('status', BookingStatus::PendingPayment->value)
                        ->where('created_at', '>', now()->subMinutes($lockExpiryMinutes))
                )
                ->latest()
                ->first();

            if ($resumableLock) {
                return $resumableLock;
            }

            $this->inventoryService->lock($propertyRoom, $checkIn, $checkOut, $quantity);

            return InventoryLock::query()->create([
                'property_id' => $property->id,
                'property_room_id' => $propertyRoom->id,
                'user_id' => $user->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'quantity' => $quantity,
                'expires_at' => $expiresAt,
                'status' => InventoryLockStatus::Active,
                'platform' => $platform,
            ]);
        });

        // For resumed converted locks, the real deadline is the booking's retry window,
        // not the lock's original expires_at (which may already be in the past).
        $effectiveExpiresAt = $lock->expires_at;
        if ($lock->status === InventoryLockStatus::Converted && $lock->booking) {
            $effectiveExpiresAt = $lock->booking->created_at->copy()->addMinutes($lockExpiryMinutes);
        }

        $expiresInSeconds = max(0, (int) now()->diffInSeconds($effectiveExpiresAt, false));

        return [
            'lock_id' => $lock->id,
            'property_id' => $lock->property_id,
            'property_room_id' => $lock->property_room_id,
            'check_in' => $lock->check_in->toDateString(),
            'check_out' => $lock->check_out->toDateString(),
            'rooms' => $lock->quantity,
            'expires_at' => $effectiveExpiresAt->toISOString(),
            'expires_in_seconds' => $expiresInSeconds,
            'status' => $lock->status->value,
        ];
    }

    /**
     * Confirm a booking from an existing active lock.
     *
     * @param  array{lock_id: int, payment_method: string}  $data
     * @return array<string, mixed>
     */
    public function confirmBookingFromLock(User $user, array $data): array
    {
        $booking = DB::transaction(function () use ($user, $data) {
            /** @var InventoryLock|null $lock */
            $lock = InventoryLock::query()
                ->with(['property.country', 'property.refCity', 'property.refState', 'propertyRoom'])
                ->lockForUpdate()
                ->find($data['lock_id']);

            if (! $lock) {
                throw ValidationException::withMessages([
                    'lock_id' => 'Reservation lock not found.',
                ]);
            }

            if ($lock->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'lock_id' => 'This reservation lock does not belong to the current user.',
                ]);
            }

            if ($lock->status === InventoryLockStatus::Converted) {
                throw ValidationException::withMessages([
                    'lock_id' => 'This reservation is no longer available. It may have already been booked or is unavailable at this time. Please try again later.',
                ]);
            }

            if ($lock->status !== InventoryLockStatus::Active) {
                throw ValidationException::withMessages([
                    'lock_id' => 'This reservation is no longer active.',
                ]);
            }

            if ($lock->expires_at->isPast()) {
                $this->inventoryService->expireLock($lock);

                throw ValidationException::withMessages([
                    'lock_id' => 'This reservation lock has expired. Please reserve again.',
                ]);
            }

            if (! $lock->property || ! $lock->propertyRoom || $lock->property->status !== PropertyStatus::Active) {
                throw ValidationException::withMessages([
                    'lock_id' => 'The selected property is no longer available for booking.',
                ]);
            }

            // Check Guest Capacity
            $this->assertCapacityAllowed($lock->propertyRoom, $lock->quantity, (int) $data['adults'], (int) $data['children']);

            $this->inventoryService->convertLockedToBooking($lock);

            $nights = $this->calculatePricingAction->nights($lock->check_in->toDateString(), $lock->check_out->toDateString());
            $pricing = $this->calculatePricingAction->handle($lock->propertyRoom, $nights, $lock->quantity, $lock->property->country_id, $lock->property->property_type_id);
            $country = $lock->property->country;

            $discountAmount = 0.0;
            $couponId = null;
            $promoId = null;
            $appliedPromo = null;

            if (! empty($data['coupon_code'])) {
                // First try to validate as promo code (admin-created)
                try {
                    $promo = app(PromoCodeService::class)->validatePromoCode(
                        $data['coupon_code'],
                        $user,
                        (float) $pricing['total_amount'],
                        $lock->property->country_id,
                        $lock->property->ref_city_id,
                    );

                    $discountAmount = app(PromoCodeService::class)->calculateDiscount($promo, (float) $pricing['base_amount']);
                    $promoId = $promo->id;
                    $appliedPromo = $promo;
                } catch (\Exception $promoException) {
                    // If promo code validation fails, try as coupon (user voucher)
                    try {
                        $coupon = app(CouponService::class)->validateCoupon(
                            $data['coupon_code'],
                            $user,
                            (float) $pricing['total_amount'],
                        );

                        $discountAmount = app(CouponService::class)->calculateDiscount($coupon, (float) $pricing['base_amount']);
                        $couponId = $coupon->id;
                    } catch (\Exception $couponException) {
                        throw ValidationException::withMessages([
                            'coupon_code' => 'Invalid coupon or promo code.',
                        ]);
                    }
                }
            }

            // Recompute with the resolved discount so tax is calculated on the
            // discounted room amount, not the original — base_amount is unaffected.
            $pricing = $this->calculatePricingAction->handle($lock->propertyRoom, $nights, $lock->quantity, $lock->property->country_id, $lock->property->property_type_id, $discountAmount);
            $finalTotal = (float) $pricing['total_amount'];

            // A fully-discounted booking has nothing to pay, so no payment
            // method or gateway is involved: store no method and mark it paid.
            // Only when money is actually owed do we enforce a payment method —
            // this endpoint handles pay-at-property bookings only (pay_online
            // goes through the gateway endpoint).
            $isFree = $finalTotal <= 0.0;

            if (! $isFree) {
                $paymentMethod = $data['payment_method'] ?? null;

                if ($paymentMethod === PaymentMethod::PayOnline->value) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'For online payments, use the payment initiation endpoint instead.',
                    ]);
                }

                if ($paymentMethod !== PaymentMethod::PayAtProperty->value) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'A payment method is required for this booking.',
                    ]);
                }

                if (! $lock->property->pay_at_property) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'This property is not available for pay at property booking.',
                    ]);
                }

                // Commission is charged on the full undiscounted base_amount, but Pay at
                // Property collects nothing online — nothing would ever cover that
                // commission for a discounted booking. Single-mode/no-partner properties
                // have no wallet-crediting concept at all (see checkIn()'s own gate), so
                // this only ever applies to a multi-mode, partner-owned property.
                // Toggle: config('app.require_full_payment_for_discounted_bookings').
                if (config('app.require_full_payment_for_discounted_bookings')
                    && $discountAmount > 0 && SystemMode::isMulti() && $lock->property->partner_id) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Pay at Property is not available when a discount is applied — please pay online in full.',
                    ]);
                }
            }

            $guestDialCode = $data['guest_dial_code'] ?? null;
            if (empty($guestDialCode)) {
                if ($user->email === $data['guest_email']) {
                    $guestDialCode = $user->dial_code;
                } else {
                    $guestDialCode = User::where('email', $data['guest_email'])->first()?->dial_code;
                }
            }
            if (empty($guestDialCode) && $lock->property?->country?->phone_code) {
                $phoneCode = $lock->property->country->phone_code;
                $guestDialCode = str_starts_with($phoneCode, '+') ? $phoneCode : '+'.$phoneCode;
            }
            $guestDialCode = $this->normalizeDialCode($guestDialCode);

            $commissionRate = null;
            $commissionAmount = null;
            $commissionRateId = null;
            $commissionSource = null;
            if (SystemMode::isMulti()) {
                $rateDetails = app(CommissionService::class)->resolveRateWithDetails(
                    $lock->property->country_id,
                    $lock->property->partner_id,
                    $lock->property->property_type_id ?? 0,
                );
                $commissionRate = $rateDetails['rate'];
                $commissionRateId = $rateDetails['rate_id'];
                $commissionSource = $rateDetails['source'];
                $commissionAmount = app(CommissionService::class)->calculateCommission((float) $pricing['base_amount'], $commissionRate);
            }

            $booking = Booking::query()->create([
                'booking_number' => $this->generateBookingNumber(),
                'property_id' => $lock->property_id,
                'property_room_id' => $lock->property_room_id,
                'user_id' => $user->id,
                'booked_by' => null,
                'check_in' => $lock->check_in->toDateString(),
                'check_out' => $lock->check_out->toDateString(),
                'total_nights' => $nights,
                'adults' => (int) $data['adults'],
                'children' => (int) $data['children'],
                'has_pets' => (bool) $data['has_pets'],
                'booked_rooms' => $lock->quantity,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'],
                'guest_dial_code' => $guestDialCode,
                'room_number' => null,
                'price_per_night' => $lock->propertyRoom->base_price_per_night,
                'currency_code' => $country?->currency_code,
                'currency_symbol' => $country?->currency_symbol,
                'tax_details' => $pricing['tax_details'],
                'base_amount' => $pricing['base_amount'],
                'tax_amount' => $pricing['tax_amount'],
                'discount_amount' => $discountAmount,
                'total_amount' => $finalTotal,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'commission_rate_id' => $commissionRateId,
                'commission_source' => $commissionSource,
                'booked_country_id' => $lock->property->country_id,
                'booked_property_type_id' => $lock->property->property_type_id,
                'booking_source' => in_array($lock->platform ?? 'web', ['android', 'ios'], true) ? BookingSource::Application : BookingSource::Website,
                'payment_status' => $isFree ? PaymentStatus::Paid : PaymentStatus::Unpaid,
                'payment_method' => $isFree ? null : PaymentMethod::PayAtProperty,
                'transaction_id' => null,
                'status' => BookingStatus::Confirmed,
                'coupon_id' => $couponId,
                'promo_code_id' => $promoId,
                'cancellation_policy_snapshot' => app(CancellationPolicyService::class)->buildSnapshot($lock->property, $lock->check_in->toDateString()),
            ]);

            if ($appliedPromo) {
                $appliedPromo->increment('used_count');
            }

            // Mark coupon as used if applicable
            if ($couponId) {
                $coupon = app(CouponService::class)->validateCoupon(
                    $data['coupon_code'],
                    $user,
                    (float) $pricing['total_amount'],
                );
                app(CouponService::class)->useCoupon($coupon);
            }

            $lock->update([
                'booking_id' => $booking->id,
                'status' => InventoryLockStatus::Converted,
            ]);

            return $booking->fresh(['property.country', 'property.primaryImages', 'propertyRoom.roomType']);
        });

        $pricingData = [
            'subtotal' => (float) $booking->base_amount,
            'tax_amount' => (float) $booking->tax_amount,
            'discount_amount' => (float) $booking->discount_amount,
            'total_amount' => (float) $booking->total_amount,
            'currency_code' => $booking->currency_code,
            'currency_symbol' => $booking->currency_symbol,
            'tax_details' => $booking->tax_details,
        ];

        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->base_amount, $booking->currency_code, 'subtotal');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->tax_amount, $booking->currency_code, 'tax_amount');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->discount_amount, $booking->currency_code, 'discount_amount');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->total_amount, $booking->currency_code, 'total_amount');

        // Send booking confirmation email to guest
        if ($booking->guest_email) {
            Mail::to($booking->guest_email)->queue(new BookingConfirmationMailable($booking));
        }

        $this->sendBookingNotificationsAction->sendNew($booking);

        return [
            'booking' => [
                'id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'status' => $booking->status->value,
                'payment_status' => $booking->payment_status->value,
                'payment_method' => $booking->payment_method?->value,
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
                'property' => [
                    'name' => $booking->property?->name,
                    'slug' => $booking->property?->slug,
                    'street_address' => $this->formatFullAddress($booking->property),
                    'primary_image' => ($booking->property?->primaryImages->firstWhere('media_type', 'image') ?? $booking->property?->primaryImages->first())?->image_path
                        ? asset('storage/'.($booking->property->primaryImages->firstWhere('media_type', 'image') ?? $booking->property->primaryImages->first())->image_path)
                        : null,
                ],
                'room_type_name' => $booking->propertyRoom?->roomType?->name,
                'pricing' => $pricingData,
            ],
            'lock' => [
                'id' => (int) $data['lock_id'],
                'status' => InventoryLockStatus::Converted->value,
            ],
        ];
    }

    /**
     * Create a booking with gateway payment initiation.
     *
     * This method creates a booking in PENDING_PAYMENT status and initiates payment via gateway.
     * The booking will be confirmed when webhook payment succeeds.
     *
     * @param  array{lock_id: int, payment_method: string, gateway_type: string, payment_type: string, adults: int, children: int, has_pets: bool, guest_name: string, guest_email: string, guest_phone: string, guest_dial_code: ?string, coupon_code: ?string}  $data
     * @return array<string, mixed>
     */
    public function createBookingWithPayment(User $user, array $data): array
    {
        if ($data['payment_method'] === PaymentMethod::PayAtProperty->value) {
            throw ValidationException::withMessages([
                'payment_method' => 'Use confirmBookingFromLock for pay at property.',
            ]);
        }

        // If the lock points to a still-resumable PendingPayment booking (user came back
        // from the payment gateway without paying), retry payment on that booking instead
        // of creating a duplicate. Returns the same response shape as a fresh booking.
        $retryMinutes = (int) config('app.inventory_lock_expiry_minutes', 10);
        $existingLock = InventoryLock::query()
            ->with('booking')
            ->where('user_id', $user->id)
            ->find($data['lock_id']);

        if (
            $existingLock
            && $existingLock->status === InventoryLockStatus::Converted
            && $existingLock->booking?->status === BookingStatus::PendingPayment
            && $existingLock->booking->created_at->gt(now()->subMinutes($retryMinutes))
        ) {
            return $this->retryBookingPayment($user, $existingLock->booking->booking_number, [
                'gateway_type' => $data['gateway_type'],
                'payment_type' => $data['payment_type'],
            ]);
        }

        // Transaction 1: validate, create booking, convert lock — commits before payment is attempted.
        // This ensures the booking persists even if the payment gateway fails, allowing retry.
        ['booking' => $booking, 'paymentAmount' => $paymentAmount, 'finalTotal' => $finalTotal, 'lock' => $lock] = DB::transaction(function () use ($user, $data) {
            /** @var InventoryLock|null $lock */
            $lock = InventoryLock::query()
                ->with(['property.country', 'property.refCity', 'property.refState', 'propertyRoom'])
                ->lockForUpdate()
                ->find($data['lock_id']);

            if (! $lock) {
                throw ValidationException::withMessages([
                    'lock_id' => 'Reservation lock not found.',
                ]);
            }

            if ($lock->user_id !== $user->id) {
                throw ValidationException::withMessages([
                    'lock_id' => 'This reservation lock does not belong to the current user.',
                ]);
            }

            if ($lock->status === InventoryLockStatus::Converted) {
                throw ValidationException::withMessages([
                    'lock_id' => 'This reservation is no longer available. It may have already been booked or is unavailable at this time. Please try again later.',
                ]);
            }

            if ($lock->status !== InventoryLockStatus::Active) {
                throw ValidationException::withMessages([
                    'lock_id' => 'This reservation is no longer active.',
                ]);
            }

            if ($lock->expires_at->isPast()) {
                $this->inventoryService->expireLock($lock);
                throw ValidationException::withMessages([
                    'lock_id' => 'This reservation lock has expired. Please reserve again.',
                ]);
            }

            if (! $lock->property || ! $lock->propertyRoom || $lock->property->status !== PropertyStatus::Active) {
                throw ValidationException::withMessages([
                    'lock_id' => 'The selected property is no longer available for booking.',
                ]);
            }

            $this->assertCapacityAllowed($lock->propertyRoom, $lock->quantity, (int) $data['adults'], (int) $data['children']);

            $this->inventoryService->convertLockedToBooking($lock);

            $nights = $this->calculatePricingAction->nights($lock->check_in->toDateString(), $lock->check_out->toDateString());
            $pricing = $this->calculatePricingAction->handle($lock->propertyRoom, $nights, $lock->quantity, $lock->property->country_id, $lock->property->property_type_id);
            $country = $lock->property->country;

            $discountAmount = 0.0;
            $couponId = null;
            $promoId = null;
            $appliedPromo = null;

            if (! empty($data['coupon_code'])) {
                // First try to validate as promo code (admin-created)
                try {
                    $promo = app(PromoCodeService::class)->validatePromoCode(
                        $data['coupon_code'],
                        $user,
                        (float) $pricing['total_amount'],
                        $lock->property->country_id,
                        $lock->property->ref_city_id,
                    );

                    $discountAmount = app(PromoCodeService::class)->calculateDiscount($promo, (float) $pricing['base_amount']);
                    $promoId = $promo->id;
                    $appliedPromo = $promo;
                } catch (\Exception $promoException) {
                    // If promo code validation fails, try as coupon (user voucher)
                    try {
                        $coupon = app(CouponService::class)->validateCoupon(
                            $data['coupon_code'],
                            $user,
                            (float) $pricing['total_amount'],
                        );

                        $discountAmount = app(CouponService::class)->calculateDiscount($coupon, (float) $pricing['base_amount']);
                        $couponId = $coupon->id;
                    } catch (\Exception $couponException) {
                        throw ValidationException::withMessages([
                            'coupon_code' => 'Invalid coupon or promo code.',
                        ]);
                    }
                }
            }

            // Recompute with the resolved discount so tax is calculated on the
            // discounted room amount, not the original — base_amount is unaffected.
            $pricing = $this->calculatePricingAction->handle($lock->propertyRoom, $nights, $lock->quantity, $lock->property->country_id, $lock->property->property_type_id, $discountAmount);
            $finalTotal = (float) $pricing['total_amount'];

            // Commission is charged on the full undiscounted base_amount, but a partial
            // payment only collects advance_percentage% of the discounted total online —
            // for a discounted booking that can fall short of covering commission owed.
            // Single-mode/no-partner properties have no wallet-crediting concept at all
            // (see checkIn()'s own gate), so this only ever applies to a multi-mode,
            // partner-owned property.
            // Toggle: config('app.require_full_payment_for_discounted_bookings').
            if (config('app.require_full_payment_for_discounted_bookings')
                && $discountAmount > 0 && $data['payment_type'] === 'partial' && SystemMode::isMulti() && $lock->property->partner_id) {
                throw ValidationException::withMessages([
                    'payment_type' => 'Partial payment is not available when a discount is applied — please pay online in full.',
                ]);
            }

            // Calculate payment amount (full or partial)
            $paymentAmount = $finalTotal;
            if ($data['payment_type'] === 'partial') {
                // Use property's advance_percentage for partial payment
                $advancePercentage = (float) ($lock->property->advance_percentage ?? 20);
                $paymentAmount = round($finalTotal * $advancePercentage / 100, 2);
            }

            // Create booking in PENDING_PAYMENT status
            $guestDialCode = $data['guest_dial_code'] ?? null;
            if (empty($guestDialCode)) {
                if ($user->email === $data['guest_email']) {
                    $guestDialCode = $user->dial_code;
                } else {
                    $guestDialCode = User::where('email', $data['guest_email'])->first()?->dial_code;
                }
            }
            if (empty($guestDialCode) && $lock->property?->country?->phone_code) {
                $phoneCode = $lock->property->country->phone_code;
                $guestDialCode = str_starts_with($phoneCode, '+') ? $phoneCode : '+'.$phoneCode;
            }
            $guestDialCode = $this->normalizeDialCode($guestDialCode);

            $commissionRate = null;
            $commissionAmount = null;
            $commissionRateId = null;
            $commissionSource = null;
            if (SystemMode::isMulti()) {
                $rateDetails = app(CommissionService::class)->resolveRateWithDetails(
                    $lock->property->country_id,
                    $lock->property->partner_id,
                    $lock->property->property_type_id ?? 0,
                );
                $commissionRate = $rateDetails['rate'];
                $commissionRateId = $rateDetails['rate_id'];
                $commissionSource = $rateDetails['source'];
                $commissionAmount = app(CommissionService::class)->calculateCommission((float) $pricing['base_amount'], $commissionRate);
            }

            $booking = Booking::query()->create([
                'booking_number' => $this->generateBookingNumber(),
                'property_id' => $lock->property_id,
                'property_room_id' => $lock->property_room_id,
                'user_id' => $user->id,
                'booked_by' => null,
                'check_in' => $lock->check_in->toDateString(),
                'check_out' => $lock->check_out->toDateString(),
                'total_nights' => $nights,
                'adults' => (int) $data['adults'],
                'children' => (int) $data['children'],
                'has_pets' => (bool) $data['has_pets'],
                'booked_rooms' => $lock->quantity,
                'guest_name' => $data['guest_name'],
                'guest_email' => $data['guest_email'],
                'guest_phone' => $data['guest_phone'],
                'guest_dial_code' => $guestDialCode,
                'room_number' => null,
                'price_per_night' => $lock->propertyRoom->base_price_per_night,
                'currency_code' => $country?->currency_code,
                'currency_symbol' => $country?->currency_symbol,
                'tax_details' => $pricing['tax_details'],
                'base_amount' => $pricing['base_amount'],
                'tax_amount' => $pricing['tax_amount'],
                'discount_amount' => $discountAmount,
                'total_amount' => $finalTotal,
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'commission_rate_id' => $commissionRateId,
                'commission_source' => $commissionSource,
                'booked_country_id' => $lock->property->country_id,
                'booked_property_type_id' => $lock->property->property_type_id,
                'booking_source' => in_array($lock->platform ?? 'web', ['android', 'ios'], true) ? BookingSource::Application : BookingSource::Website,
                'payment_status' => PaymentStatus::Unpaid,
                'payment_method' => PaymentMethod::PayOnline,
                'transaction_id' => null,
                'status' => BookingStatus::PendingPayment,
                'coupon_id' => $couponId,
                'promo_code_id' => $promoId,
                'cancellation_policy_snapshot' => app(CancellationPolicyService::class)->buildSnapshot($lock->property, $lock->check_in->toDateString()),
            ]);

            // Neither the coupon nor the promo's used_count is marked here — booking is
            // PendingPayment. Both are marked used in confirmBookingFromPayment() after
            // payment succeeds, so an abandoned/expired checkout never consumes either.

            $lock->update([
                'booking_id' => $booking->id,
                'status' => InventoryLockStatus::Converted,
            ]);

            return [
                'booking' => $booking,
                'paymentAmount' => $paymentAmount,
                'finalTotal' => $finalTotal,
                'lock' => $lock,
            ];
        });

        // Load relationships needed for the response (booking was created inside transaction)
        $booking->load(['property.primaryImages', 'propertyRoom.roomType']);

        $pricingData = [
            'subtotal' => (float) $booking->base_amount,
            'tax_amount' => (float) $booking->tax_amount,
            'discount_amount' => (float) $booking->discount_amount,
            'total_amount' => (float) $booking->total_amount,
            'currency_code' => $booking->currency_code,
            'currency_symbol' => $booking->currency_symbol,
            'tax_details' => $booking->tax_details,
        ];

        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->base_amount, $booking->currency_code, 'subtotal');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->tax_amount, $booking->currency_code, 'tax_amount');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->discount_amount, $booking->currency_code, 'discount_amount');
        $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->total_amount, $booking->currency_code, 'total_amount');

        $bookingData = [
            'id' => $booking->id,
            'booking_number' => $booking->booking_number,
            'status' => $booking->status->value,
            'payment_status' => $booking->payment_status->value,
            'payment_method' => $booking->payment_method?->value,
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
            'property' => [
                'name' => $booking->property?->name,
                'slug' => $booking->property?->slug,
                'street_address' => $this->formatFullAddress($booking->property),
                'primary_image' => ($booking->property?->primaryImages->firstWhere('media_type', 'image') ?? $booking->property?->primaryImages->first())?->image_path
                    ? asset('storage/'.($booking->property->primaryImages->firstWhere('media_type', 'image') ?? $booking->property->primaryImages->first())->image_path)
                    : null,
            ],
            'room_type_name' => $booking->propertyRoom?->roomType?->name,
            'pricing' => $pricingData,
        ];

        // Transaction 2 (inside PaymentService): initiate gateway payment.
        // Runs outside the booking transaction so a gateway failure does not roll back the booking.
        try {
            $payment = app(PaymentService::class)->createPayment([
                'booking_id' => $booking->id,
                'gateway_type' => $data['gateway_type'],
                'payment_type' => $data['payment_type'],
                'amount' => $paymentAmount,
                'total_amount' => $finalTotal,
                'currency' => $booking->currency_code,
                'country_id' => $lock->property->country_id,
            ]);
        } catch (PaymentGatewayException $e) {
            return [
                'booking' => $bookingData,
                'payment' => null,
                'payment_error' => $e->getMessage(),
            ];
        }

        return [
            'booking' => $bookingData,
            'payment' => [
                'id' => $payment->id,
                'gateway_order_id' => $payment->gateway_order_id,
                'payment_url' => $payment->gateway_response['payment_url'] ?? null,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'payment_type' => $payment->payment_type->value,
                'remaining_amount' => $payment->remaining_amount,
                'status' => $payment->status->value,
            ],
        ];
    }

    /**
     * Retry payment for an existing booking.
     *
     * @param  array{gateway_type?: string, payment_type?: string}  $options
     * @return array<string, mixed>
     */
    public function retryBookingPayment(User $user, string $bookingNumber, array $options = []): array
    {
        // 1. Find booking
        $booking = Booking::where('booking_number', $bookingNumber)
            ->where('user_id', $user->id)
            ->with(['property.country', 'property.refCity', 'property.refState', 'propertyRoom.roomType'])
            ->first();

        if (! $booking) {
            throw ValidationException::withMessages([
                'booking_number' => 'Booking not found.',
            ]);
        }

        // 2. Validate booking status
        $allowedStatuses = [BookingStatus::PendingPayment->value];
        if (! in_array($booking->status->value, $allowedStatuses)) {
            $message = match ($booking->status->value) {
                BookingStatus::Confirmed->value => 'Booking is already confirmed. Payment was successful.',
                BookingStatus::Cancelled->value => 'Booking is cancelled. Cannot retry payment.',
                BookingStatus::Expired->value => 'Booking has expired. Please make a new booking.',
                BookingStatus::CheckedIn->value => 'Guest is already checked in.',
                BookingStatus::Completed->value => 'Booking is already completed.',
                default => 'Booking status does not allow payment retry.',
            };
            throw ValidationException::withMessages([
                'booking_number' => $message,
            ]);
        }

        // 3. Validate booking age (must be within lock expiry window)
        // Uses same config as ghost booking expiry to ensure consistency
        $retryMinutesLimit = config('app.inventory_lock_expiry_minutes', 10);
        if ($booking->created_at->lt(now()->subMinutes($retryMinutesLimit))) {
            throw ValidationException::withMessages([
                'booking_number' => "Retry is only allowed within {$retryMinutesLimit} minutes of booking creation.",
            ]);
        }

        // 4. Validate property is still active
        if ($booking->property->status !== PropertyStatus::Active) {
            throw ValidationException::withMessages([
                'booking_number' => 'The property is no longer available. Please contact support.',
            ]);
        }

        // 5. Check if latest payment is still Pending (mark as Failed to allow retry)
        $latestPayment = $booking->payments()->latest()->first();

        if ($latestPayment?->status === PaymentTransactionStatus::Pending) {
            // Mark pending payment as Failed since user is explicitly retrying, and
            // best-effort cancel it at the gateway so the old link/session can't be
            // completed later and go unmatched by a webhook.
            app(PaymentService::class)->failPaymentForRetry($latestPayment);
        }

        // 6. Find most recent failed payment (only Failed, not Pending)
        $payment = $booking->payments()
            ->where('status', PaymentTransactionStatus::Failed->value)
            ->latest()
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'booking_number' => 'No failed payment found for this booking.',
            ]);
        }

        // 8. Rate limiting - prevent too many retry attempts
        $retryLimit = config('app.payment_retry_limit', 5);
        $retryCount = $booking->payments()
            ->where('status', PaymentTransactionStatus::Failed->value)
            ->count();

        if ($retryCount >= $retryLimit) {
            throw ValidationException::withMessages([
                'booking_number' => "Maximum retry attempts ({$retryLimit}) exceeded. Please contact support.",
            ]);
        }

        // 9. Handle gateway_type change (if provided in options)
        $gatewayType = $options['gateway_type'] ?? $payment->gateway_type->value;
        $paymentType = $options['payment_type'] ?? $payment->payment_type->value;

        // Commission is charged on the full undiscounted base_amount — switching to
        // partial after a discount was applied at creation would reopen the exact
        // shortfall the creation-time guard already prevents. Single-mode/no-partner
        // properties have no wallet-crediting concept at all (see checkIn()'s own gate),
        // so this only ever applies to a multi-mode, partner-owned property.
        // Toggle: config('app.require_full_payment_for_discounted_bookings').
        if (config('app.require_full_payment_for_discounted_bookings')
            && $paymentType === 'partial' && (float) $booking->discount_amount > 0 && SystemMode::isMulti() && $booking->property->partner_id) {
            throw ValidationException::withMessages([
                'payment_type' => 'Partial payment is not available when a discount is applied — please pay online in full.',
            ]);
        }

        // If gateway changed, validate new gateway settings
        if ($gatewayType !== $payment->gateway_type->value) {
            $settings = PaymentGatewaySetting::query()
                ->where('gateway_type', $gatewayType)
                ->where('country_id', $booking->property->country_id)
                ->where('is_active', true)
                ->first();

            if (! $settings) {
                throw ValidationException::withMessages([
                    'gateway_type' => 'Selected payment gateway is not available.',
                ]);
            }
        }

        // 10. Recalculate amount if payment_type changed
        $paymentAmount = $payment->amount;
        $totalAmount = $booking->total_amount;

        if ($paymentType !== $payment->payment_type->value) {
            if ($paymentType === 'full') {
                $paymentAmount = $totalAmount;
            } else {
                $advancePercentage = (float) ($booking->property->advance_percentage ?? 20);
                $paymentAmount = round($totalAmount * $advancePercentage / 100, 2);
            }
        }

        // 11. Call PaymentService->retryPayment with updated options
        try {
            $newPayment = app(PaymentService::class)->retryPayment($payment);

            // Update payment if gateway or payment_type changed
            if ($gatewayType !== $payment->gateway_type->value || $paymentType !== $payment->payment_type->value) {
                $newPayment->update([
                    'gateway_type' => $gatewayType,
                    'payment_type' => $paymentType,
                    'amount' => $paymentAmount,
                    'remaining_amount' => $paymentType === 'partial' ? ($totalAmount - $paymentAmount) : null,
                ]);

                // Re-initiate gateway payment with new settings
                $settings = PaymentGatewaySetting::query()
                    ->where('gateway_type', $gatewayType)
                    ->where('country_id', $booking->property->country_id)
                    ->where('is_active', true)
                    ->first();

                if (! $settings) {
                    throw new PaymentGatewayException('Gateway settings not found.');
                }

                $provider = app(PaymentProviderFactory::class)->make($gatewayType, $settings);
                $result = $provider->createPayment($newPayment);

                $newPayment->update([
                    'gateway_order_id' => $result['gateway_order_id'],
                    'gateway_payment_id' => $result['gateway_payment_id'] ?? null,
                    'gateway_response' => array_merge($result['raw'], ['payment_url' => $result['payment_url'] ?? null]),
                ]);
            }

            $pricingData = [
                'subtotal' => (float) $booking->base_amount,
                'tax_amount' => (float) $booking->tax_amount,
                'discount_amount' => (float) $booking->discount_amount,
                'total_amount' => (float) $booking->total_amount,
                'currency_code' => $booking->currency_code,
                'currency_symbol' => $booking->currency_symbol,
                'tax_details' => $booking->tax_details,
            ];

            $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->base_amount, $booking->currency_code, 'subtotal');
            $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->tax_amount, $booking->currency_code, 'tax_amount');
            $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->discount_amount, $booking->currency_code, 'discount_amount');
            $pricingData = CurrencyConverter::addConvertedPrice($pricingData, (float) $booking->total_amount, $booking->currency_code, 'total_amount');

            return [
                'booking' => [
                    'id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'status' => $booking->status->value,
                    'payment_status' => $booking->payment_status->value,
                    'payment_method' => $booking->payment_method?->value,
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
                    'property' => [
                        'name' => $booking->property?->name,
                        'slug' => $booking->property?->slug,
                        'street_address' => $this->formatFullAddress($booking->property),
                        'primary_image' => ($booking->property?->primaryImages->firstWhere('media_type', 'image') ?? $booking->property?->primaryImages->first())?->image_path
                            ? asset('storage/'.($booking->property->primaryImages->firstWhere('media_type', 'image') ?? $booking->property->primaryImages->first())->image_path)
                            : null,
                    ],
                    'room_type_name' => $booking->propertyRoom?->roomType?->name,
                    'pricing' => $pricingData,
                ],
                'payment' => [
                    'id' => $newPayment->id,
                    'gateway_order_id' => $newPayment->gateway_order_id,
                    'payment_url' => $newPayment->gateway_response['payment_url'] ?? null,
                    'amount' => $newPayment->amount,
                    'currency' => $newPayment->currency,
                    'payment_type' => $newPayment->payment_type->value,
                    'remaining_amount' => $newPayment->remaining_amount,
                    'status' => $newPayment->status->value,
                ],
            ];
        } catch (PaymentGatewayException $e) {
            throw ValidationException::withMessages([
                'payment' => $e->getMessage(),
            ]);
        }
    }

    // ── Booking CRUD ───────────────────────────────────────────────────────

    /**
     * Create a new booking (admin flow — immediate confirmation with inventory check).
     *
     * @param  array{property_room_id: int, user_id: int, check_in: string, check_out: string, adults: int, children: int, has_pets: bool, booked_rooms: int, room_number: ?string, payment_method: ?string, transaction_id: ?string, payment_status: string}  $data
     *
     * @throws \RuntimeException If insufficient inventory
     */
    public function createBooking(Property $property, array $data, ?User $admin = null): Booking
    {
        $propertyRoom = PropertyRoom::query()->findOrFail($data['property_room_id']);

        // Check Guest Capacity
        $this->assertCapacityAllowed($propertyRoom, (int) ($data['booked_rooms'] ?? 1), (int) ($data['adults'] ?? 1), (int) ($data['children'] ?? 0));

        $nights = $this->calculatePricingAction->nights($data['check_in'], $data['check_out']);
        $pricing = $this->calculatePricingAction->handle($propertyRoom, $nights, (int) $data['booked_rooms'], $property->country_id, $property->property_type_id);
        $quantity = (int) ($data['booked_rooms'] ?? 1);

        $country = $property->country;
        $customer = User::query()->findOrFail($data['user_id']);

        $booking = DB::transaction(function () use ($property, $propertyRoom, $data, $admin, $customer, $nights, $pricing, $quantity, $country) {
            // Check and reserve inventory (atomic with lockForUpdate)
            $this->inventoryService->reserve($propertyRoom, $data['check_in'], $data['check_out'], $quantity);

            $commissionRate = null;
            $commissionAmount = null;
            $commissionRateId = null;
            $commissionSource = null;
            if (SystemMode::isMulti()) {
                $rateDetails = app(CommissionService::class)->resolveRateWithDetails(
                    $property->country_id,
                    $property->partner_id,
                    $property->property_type_id ?? 0,
                );
                $commissionRate = $rateDetails['rate'];
                $commissionRateId = $rateDetails['rate_id'];
                $commissionSource = $rateDetails['source'];
                $commissionAmount = app(CommissionService::class)->calculateCommission((float) $pricing['base_amount'], $commissionRate);
            }

            $booking = Booking::query()->create([
                'booking_number' => $this->generateBookingNumber(),
                'property_id' => $property->id,
                'property_room_id' => $data['property_room_id'],
                'user_id' => $data['user_id'],
                'booked_by' => $admin?->id,
                'check_in' => $data['check_in'],
                'check_out' => $data['check_out'],
                'total_nights' => $nights,
                'adults' => $data['adults'] ?? 1,
                'children' => $data['children'] ?? 0,
                'has_pets' => $data['has_pets'] ?? false,
                'booked_rooms' => $quantity,
                'room_number' => $data['room_number'] ?? null,
                'guest_name' => $customer->name,
                'guest_email' => $customer->email,
                'guest_phone' => $customer->phone,
                'guest_dial_code' => $customer->dial_code ?: ($country?->phone_code ? (str_starts_with($country->phone_code, '+') ? $country->phone_code : '+'.$country->phone_code) : null),
                'price_per_night' => $propertyRoom->base_price_per_night,
                'currency_code' => $country?->currency_code,
                'currency_symbol' => $country?->currency_symbol,
                'tax_details' => $pricing['tax_details'],
                'base_amount' => $pricing['base_amount'],
                'tax_amount' => $pricing['tax_amount'],
                'discount_amount' => 0,
                'total_amount' => $pricing['total_amount'],
                'commission_rate' => $commissionRate,
                'commission_amount' => $commissionAmount,
                'commission_rate_id' => $commissionRateId,
                'commission_source' => $commissionSource,
                'booked_country_id' => $property->country_id,
                'booked_property_type_id' => $property->property_type_id,
                'booking_source' => match (true) {
                    $admin === null => BookingSource::Website,
                    $admin->role === UserRole::Partner => BookingSource::Partner,
                    default => BookingSource::Admin,
                },
                'payment_status' => $data['payment_status'] ?? PaymentStatus::Unpaid->value,
                'payment_method' => $data['payment_method'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'status' => $admin ? BookingStatus::Confirmed : BookingStatus::Pending,
                'cancellation_policy_snapshot' => app(CancellationPolicyService::class)->buildSnapshot($property, $data['check_in']),
            ]);

            if (! empty($data['payment_method']) && ($data['payment_status'] ?? null) === PaymentStatus::Paid->value) {
                Payment::create([
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'amount' => $booking->total_amount,
                    'currency' => $booking->currency_code ?? 'INR',
                    'payment_type' => PaymentType::Full,
                    'status' => PaymentTransactionStatus::Success,
                    'gateway_type' => PaymentGateway::Manual,
                    'paid_at' => now(),
                    'processed_at' => now(),
                    'metadata' => [
                        'payment_method' => $data['payment_method'],
                        'transaction_id' => $data['transaction_id'] ?? null,
                        'confirmed_by' => $admin?->id,
                    ],
                    'gateway_response' => [
                        'collected_at_property' => true,
                        'collected_by' => $admin?->id,
                        'payment_method' => $data['payment_method'],
                        'transaction_id' => $data['transaction_id'] ?? null,
                    ],
                ]);
            }

            return $booking;
        });

        // Send notifications
        $this->sendBookingNotificationsAction->sendNew($booking);

        return $booking;
    }

    /**
     * Send booking notifications to admin and customer.
     */
    /**
     * Confirm a Pending booking manually (admin cash/UPI payment).
     */
    public function confirmBooking(Booking $booking, string $paymentMethod, ?string $transactionId = null): void
    {
        $method = $paymentMethod === 'upi' ? PaymentMethod::Upi : PaymentMethod::Cash;

        DB::transaction(function () use ($booking, $method, $transactionId) {
            $booking->update([
                'status' => BookingStatus::Confirmed,
                'payment_status' => PaymentStatus::Paid,
                'payment_method' => $method,
                'transaction_id' => $transactionId,
            ]);

            // Skip Payment::create if a successful payment row already exists
            // (e.g., the edit-action flow above already recorded the manual payment).
            if ($booking->getSuccessfulPayment() !== null) {
                return;
            }

            Payment::create([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'amount' => $booking->total_amount,
                'currency' => $booking->currency_code ?? 'INR',
                'payment_type' => PaymentType::Full,
                'status' => PaymentTransactionStatus::Success,
                'gateway_type' => PaymentGateway::Manual,
                'paid_at' => now(),
                'processed_at' => now(),
                'metadata' => [
                    'payment_method' => $method->value,
                    'transaction_id' => $transactionId,
                    'confirmed_by' => auth()->id(),
                ],
                'gateway_response' => [
                    'collected_at_property' => true,
                    'collected_by' => auth()->id(),
                    'payment_method' => $method->value,
                    'transaction_id' => $transactionId,
                ],
            ]);
        });

        if ($booking->user_id) {
            try {
                $notificationService = app(NotificationService::class);
                $booking->loadMissing(['property']);

                $title = __('notifications.booking_confirmed_title');
                $body = __('notifications.booking_confirmed_body', [
                    'booking_number' => $booking->booking_number,
                    'property' => $booking->property?->name ?? 'the property',
                    'date' => $booking->check_in->format('M d, Y'),
                ]);

                $notificationData = [
                    'type' => 'booking',
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                ];

                $notificationService->send(
                    type: 'booking_confirmation',
                    title: $title,
                    body: $body,
                    userIds: $booking->user_id,
                    data: $notificationData,
                    category: NotificationCategory::BookingUpdates,
                    link: $booking->booking_number,
                    countryId: $booking->property?->country_id,
                );

                $notificationService->sendPush(
                    title: $title,
                    body: $body,
                    userIds: $booking->user_id,
                    data: $notificationData,
                    category: NotificationCategory::BookingUpdates,
                    link: $booking->booking_number,
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send booking confirmation notification', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Cancel a booking and restore inventory.
     */
    public function cancelBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $this->inventoryService->release($booking->propertyRoom, $booking->check_in, $booking->check_out, $booking->booked_rooms);

            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
        });

        $this->sendBookingNotificationsAction->sendStatusUpdate($booking, 'booking_cancelled');
    }

    /**
     * Cancel a booking with refund calculation and processing.
     */
    public function cancelBookingWithRefund(
        Booking $booking,
        ?string $reason = null,
        CancellationInitiator $cancelledBy = CancellationInitiator::Customer,
    ): array {
        // If already cancelled, return existing booking + refund state from Refund model
        if ($booking->status === BookingStatus::Cancelled) {
            $refund = Refund::whereHas('payment', function ($q) use ($booking) {
                $q->where('booking_id', $booking->id);
            })->latest()->first();

            return [
                'booking' => [
                    'booking_number' => $booking->booking_number,
                    'status' => 'cancelled',
                    'cancelled_at' => $booking->cancelled_at?->toIso8601String(),
                    'cancellation_reason' => $booking->cancellation_reason,
                ],
                'refund' => $refund ? [
                    'amount' => (float) $refund->amount,
                    'status' => $refund->status->value,
                    'refund_id' => $refund->refund_id,
                ] : null,
            ];
        }

        // Hard status checks for non-cancelled bookings
        if (in_array($booking->status, [BookingStatus::CheckedIn, BookingStatus::Completed])) {
            throw ValidationException::withMessages(['status' => 'Booking cannot be cancelled in current status']);
        }

        // Partner-initiated cancellation always gives the customer a 100% refund —
        // the customer had no fault. Penalty logic for the partner can be added here later.
        $refundPercentage = $cancelledBy === CancellationInitiator::Partner
            ? 100
            : app(CancellationPolicyService::class)->calculateRefundPercentage($booking);

        $payment = $booking->getSuccessfulPayment();

        // Single source for both the customer's refund and the partner's wallet
        // credit below, so the two can never go out of sync with each other.
        $breakdown = app(CancellationPolicyService::class)->calculateCancellationBreakdown($booking, $refundPercentage);
        $refundAmount = $breakdown['refund_amount'];

        $refund = null;

        if ($refundAmount > 0 && $payment) {
            // Check if refund already exists for this payment
            $existingRefund = Refund::where('payment_id', $payment->id)
                ->whereIn('status', [
                    RefundStatus::Pending->value,
                    RefundStatus::Processing->value,
                    RefundStatus::Completed->value,
                ])
                ->latest()
                ->first();

            if ($existingRefund) {
                $refund = $existingRefund;
            }
        }

        // Transaction limited to booking cancellation, inventory release, and creating pending refund
        $refund = DB::transaction(function () use ($booking, $reason, $cancelledBy, $refund, $refundAmount, $payment, $refundPercentage) {
            // Release inventory
            $this->inventoryService->release($booking->propertyRoom, $booking->check_in, $booking->check_out, $booking->booked_rooms);

            // Update booking status to cancelled
            $booking->update([
                'status' => BookingStatus::Cancelled,
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
                'cancelled_by' => $cancelledBy,
            ]);

            // Create pending refund record if not already exists
            if ($refundAmount > 0 && $payment && ! $refund) {
                return Refund::create([
                    'payment_id' => $payment->id,
                    'amount' => $refundAmount,
                    'refund_percentage' => $refundPercentage,
                    'status' => RefundStatus::Pending,
                    'reason' => $reason,
                ]);
            }

            return $refund;
        });

        // Credit the partner's wallet with their share of the retained cancellation fee (multi-mode only).
        // Skipped when: single-mode, nothing retained (100% refund), or property has no partner.
        if (SystemMode::isMulti() && $breakdown['retained_room'] > 0 && $booking->property?->partner_id) {
            $property = $booking->property;
            $wallet = PropertyWallet::query()->where('property_id', $property->id)->first();

            if ($wallet) {
                $commissionService = app(CommissionService::class);
                $commissionRate = (float) $booking->commission_rate;

                // Commission — like at booking creation — is always on the room
                // portion only, never on the tax that was retained alongside it.
                $retainedRoom = $breakdown['retained_room'];
                $commission = $commissionService->calculateCommission($retainedRoom, $commissionRate);
                $partnerCredit = $commissionService->calculatePartnerCredit($retainedRoom, $commission);

                if ($partnerCredit > 0) {
                    try {
                        app(PropertyWalletService::class)->credit(
                            $wallet,
                            $partnerCredit,
                            WalletTransactionReferenceType::CancellationRevenue,
                            $booking->id,
                            "{$refundPercentage}% refunded to guest — partner retained fee share",
                        );

                        $booking->update([
                            'formula_version' => 'v2',
                            'wallet_credited_at' => now(),
                            'refund_inputs' => [
                                'R' => (float) $booking->base_amount,
                                'D' => (float) $booking->discount_amount,
                                'X' => $refundPercentage,
                                'P' => $payment ? (float) $payment->amount : 0.0,
                                'C' => $commissionRate,
                                'refund_amount' => $refundAmount,
                                'retained_room' => $retainedRoom,
                                'retained_tax' => $breakdown['retained_tax'],
                                'commission' => $commission,
                                'wallet_credit' => $partnerCredit,
                            ],
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Cancellation wallet credit failed', [
                            'booking_id' => $booking->id,
                            'partner_credit' => $partnerCredit,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        }

        // Process gateway refund after transaction commit (outside transaction).
        // Skip for manual payment methods — no gateway to call; admin must manually return funds
        // and then mark the refund as complete from the booking view.
        $isManualPayment = in_array($booking->payment_method, [PaymentMethod::Cash, PaymentMethod::Upi, PaymentMethod::PayAtProperty], true);

        if ($refund && $refund->status === RefundStatus::Pending && $payment && ! $isManualPayment) {
            try {
                $refund = app(PaymentService::class)->processRefund($refund);
            } catch (\Exception $e) {
                // Log error but don't fail the cancellation
                // Refund can be retried later
                Log::error('Gateway refund failed after booking cancellation', [
                    'refund_id' => $refund->id,
                    'payment_id' => $payment->id,
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->sendBookingNotificationsAction->sendStatusUpdate($booking, 'booking_cancelled');

        return [
            'booking' => [
                'booking_number' => $booking->booking_number,
                'status' => 'cancelled',
                'cancelled_at' => $booking->cancelled_at->toIso8601String(),
                // 'cancellation_reason' => $reason,
            ],
            'refund' => $refund ? [
                'amount' => (float) $refund->amount,
                'percentage' => $refundPercentage,
                'status' => $refund->status->value,
                'refund_id' => $refund->refund_id,
            ] : null,
        ];
    }

    /**
     * Delete a booking and restore inventory (for future/ongoing bookings).
     */
    public function deleteBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            if (in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::CheckedIn])) {
                $this->inventoryService->release($booking->propertyRoom, $booking->check_in, $booking->check_out, $booking->booked_rooms);
            }

            $booking->delete();
        });
    }

    /**
     * Check-in: Confirmed → Checked-In.
     */
    public function checkIn(Booking $booking): void
    {
        if (now()->startOfDay()->lt($booking->check_in->startOfDay())) {
            throw new \RuntimeException('early_checkin_blocked');
        }

        $booking->update(['status' => BookingStatus::CheckedIn]);

        $this->sendBookingNotificationsAction->sendStatusUpdate($booking, 'booking_checked_in');

        // Notify referral service about the check-in to reward the referrer
        app(ReferralService::class)->issueReferrerReward($booking);

        // Settle partner wallet immediately on check-in (partner committed the room).
        // The cron skips bookings where wallet_credited_at is already set, so no double-credit.
        if (SystemMode::isMulti() && $booking->wallet_credited_at === null) {
            $wallet = $booking->property?->wallet;

            if ($wallet) {
                $creditAmount = app(CommissionService::class)->calculateCheckInPartnerCredit($booking);

                if ($creditAmount != 0.0) {
                    app(PropertyWalletService::class)->settleBookingCredit(
                        wallet: $wallet,
                        amount: $creditAmount,
                        referenceType: WalletTransactionReferenceType::BookingRevenue,
                        referenceId: $booking->id,
                        note: "Booking #{$booking->id} early check-in credit",
                    );
                }

                $booking->update(['wallet_credited_at' => now()]);
            }
        }
    }

    public function checkOut(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            // Release inventory for early checkout
            $today = now()->startOfDay();
            $checkOutDate = $booking->check_out->startOfDay();

            if ($today->lt($checkOutDate)) {
                $releaseStart = $today->gt($booking->check_in->startOfDay()) ? $today : $booking->check_in;
                $this->inventoryService->release(
                    $booking->propertyRoom,
                    $releaseStart,
                    $booking->check_out,
                    $booking->booked_rooms
                );
            }

            $booking->update([
                'status' => BookingStatus::Completed,
                'actual_checkout_at' => now(),
            ]);
        });

        $this->sendBookingNotificationsAction->sendStatusUpdate($booking, 'booking_completed');
    }

    // ── Inventory Management ───────────────────────────────────────────────

    public function getAvailableRooms(PropertyRoom $propertyRoom, string $checkIn, string $checkOut): int
    {
        return $this->inventoryService->getAvailableRooms($propertyRoom, $checkIn, $checkOut);
    }

    // ── Lock Management (for API/customer flow — future) ───────────────────

    // ── Pricing & Utilities ────────────────────────────────────────────────

    /**
     * Calculate pricing for a booking.
     *
     * `base_amount` is always the original, undiscounted room amount — it never
     * changes regardless of $discountAmount, since it's the figure commission is
     * calculated from elsewhere. $discountAmount only affects the amount tax is
     * calculated on: a promo discount comes off the room amount before percentage
     * tax is applied, matching what the customer is actually charged for. Fixed
     * (non-percentage) taxes are flat fees and are never affected by a discount.
     *
     * Callers that need to resolve a promo/coupon discount first (which itself
     * needs `base_amount`) should call this once with no discount to get
     * `base_amount`, resolve the discount, then call it again passing that
     * discount to get the correct `tax_amount`/`total_amount`.
     *
     * @return array{base_amount: float, tax_amount: float, total_amount: float, tax_details: array<int, array{name: string, type: string, rate: float, amount: float}>}
     */
    public function calculatePricing(PropertyRoom $propertyRoom, int $nights, int $rooms, int $countryId, ?int $propertyTypeId = null, float $discountAmount = 0.0): array
    {
        return $this->calculatePricingAction->handle($propertyRoom, $nights, $rooms, $countryId, $propertyTypeId, $discountAmount);
    }

    public function calculateNights(string $checkIn, string $checkOut): int
    {
        return $this->calculatePricingAction->nights($checkIn, $checkOut);
    }

    /**
     * Generate a unique booking number (BK-0001, BK-0002...).
     */
    public function generateBookingNumber(): string
    {
        $lastBooking = Booking::query()
            ->withTrashed()
            ->orderByDesc('id')
            ->value('booking_number');

        if ($lastBooking) {
            $lastNumber = (int) str_replace('BK-', '', $lastBooking);

            return 'BK-'.str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
        }

        return 'BK-0001';
    }

    /**
     * @return array{user: User, plain_password: ?string}
     */
    public function findOrCreateCustomer(string $phone, ?string $name = null, ?string $email = null, ?string $dialCode = null): array
    {
        return $this->findOrCreateCustomerAction->handle($phone, $name, $email, $dialCode);
    }

    private function assertPayAtPropertyAllowed(Property $property): void
    {
        if (! $property->pay_at_property) {
            throw ValidationException::withMessages([
                'property_room_id' => 'This property is not available for pay at property booking.',
            ]);
        }

        // Note: advance_percentage check removed since we now support partial payments via payment gateway
    }

    private function getBookablePropertyRoom(int $propertyRoomId): PropertyRoom
    {
        $propertyRoom = PropertyRoom::query()
            ->with([
                'property.country',
                'property.refCity',
                'property.refState',
                'roomType',
            ])
            ->find($propertyRoomId);

        if (! $propertyRoom || ! $propertyRoom->property || $propertyRoom->property->status !== PropertyStatus::Active) {
            throw ValidationException::withMessages([
                'property_room_id' => 'Selected room is not available for booking.',
            ]);
        }

        return $propertyRoom;
    }

    /**
     * Build a single-line full address string (street, city, state, zip) for a property.
     */
    private function formatFullAddress(?Property $property): ?string
    {
        if (! $property) {
            return null;
        }

        $address = implode(', ', array_filter([
            $property->street_address,
            $property->refCity?->name,
            $property->refState?->name,
            $property->zip_code,
        ]));

        return $address !== '' ? $address : null;
    }

    /**
     * Confirm booking after successful payment from webhook.
     * Updates booking status to confirmed.
     */
    public function confirmBookingFromPayment(Payment $payment): void
    {
        $booking = $payment->booking;

        if ($booking->status === BookingStatus::Confirmed) {
            return; // Already confirmed
        }

        $booking->update([
            'status' => BookingStatus::Confirmed,
            'payment_status' => $payment->payment_type === PaymentType::Partial
                ? PaymentStatus::Partial
                : PaymentStatus::Paid,
        ]);

        if ($booking->coupon_id) {
            $coupon = $booking->coupon;
            if ($coupon && ! $coupon->is_used) {
                app(CouponService::class)->useCoupon($coupon);
            }
        }

        if ($booking->promo_code_id) {
            $booking->promoCode?->increment('used_count');
        }

        // Send notifications after confirmation
        $this->sendBookingNotificationsAction->sendNew($booking);
    }

    /**
     * Assert that the guest count does not exceed room capacity.
     */
    private function assertCapacityAllowed(PropertyRoom $propertyRoom, int $rooms, int $adults, int $children): void
    {
        $maxCapacity = ($propertyRoom->roomType?->max_guests ?? 0) * $rooms;
        $totalGuests = $adults + $children;

        if ($totalGuests > $maxCapacity) {
            throw ValidationException::withMessages([
                'adults' => "This selection can only accommodate up to {$maxCapacity} guests in {$rooms} room(s).",
            ]);
        }
    }

    private function normalizeDialCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $stripped = ltrim($code, '+');

        return $stripped !== '' ? '+'.$stripped : null;
    }
}
