<?php

namespace App\Console\Commands;

use App\Actions\AutoAssignRoomsAction;
use App\Actions\CalculatePricingAction;
use App\Actions\CreatePartnerAction;
use App\Actions\GenerateRoomsAction;
use App\Enums\AnswerType;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\CancellationInitiator;
use App\Enums\CityStatus;
use App\Enums\PartnerVerificationStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Enums\PropertyCancellationPolicySource;
use App\Enums\PropertyRuleStatus;
use App\Enums\RefundStatus;
use App\Enums\RegistrationFieldScope;
use App\Enums\RegistrationFieldType;
use App\Enums\ReviewStatus;
use App\Enums\RoomTypeStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Booking;
use App\Models\City;
use App\Models\CommissionPartnerOverride;
use App\Models\CommissionRate;
use App\Models\Country;
use App\Models\Facility;
use App\Models\Floor;
use App\Models\Partner;
use App\Models\PartnerRegistrationValue;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyRule;
use App\Models\PropertyType;
use App\Models\PropertyWallet;
use App\Models\PropertyWalletTransaction;
use App\Models\RefCity;
use App\Models\Refund;
use App\Models\RegistrationField;
use App\Models\Review;
use App\Models\RoomType;
use App\Models\State;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\BookingInventoryService;
use App\Services\BookingService;
use App\Services\CancellationPolicyService;
use App\Services\CommissionService;
use App\Services\PropertyService;
use App\Services\PropertyWalletService;
use App\Services\RoomTypeService;
use App\Support\DemoAccounts;
use App\Support\SystemMode;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seeds SIX demo Partner logins, one per property type — a real partner can only have ONE
 * property_type (CreatePartnerAction/CancellationPolicyService::getAdminDefaultPolicy() both key
 * off a single fixed partner.property_type_id), so a single partner owning properties across six
 * types would be internally inconsistent with the rest of the app. PARTNER_TYPES maps each type
 * to its owning partner's email; demo-partner@gmail.com keeps its identity (see
 * App\Filament\Partner\Concerns\HasPartnerDemoGuard, which blocks its delete/edit actions, and
 * the login-page demo-credentials autofill in PartnerPanelProvider, both keyed on
 * DemoAccounts::PARTNER_EMAIL) and owns Hotel; the other five are plain, unguarded partner
 * accounts, each scoped to their own type's properties/countries from the full 30-property,
 * 329-physical-room catalog specified in eStay_30_Property_Final_Floor_Room_Inventory_Plan.docx,
 * across India/Nigeria/UAE/US.
 *
 * The document's floor/room-type/room-count structure is entirely driven by property type
 * (every Hotel uses the same 5-floor plan, every Resort the same 1-floor plan, etc. — confirmed
 * against the document's own summary table), so ROOM_PLANS below only needs one template per
 * type, applied to whichever properties have that type.
 *
 * Also seeds 5 real, login-ready guest accounts (DEMO_CUSTOMERS — password '12345678', created
 * with firstOrCreate so a team member's profile-picture customization survives future --fresh
 * runs, shared across all 6 partners rather than duplicated per partner), demo bookings/reviews
 * per property (BOOKING_PLAN: 2 confirmed, 1 checked-in, 3 completed with a review each, 1
 * cancelled), wallet credit + withdrawal-request history (reuses the real
 * wallet:credit-checked-in-bookings command, not a reimplemented formula), and 2 low-rating fake
 * reviews per property with removal requests (~80% approved/15% rejected/5% still pending) for
 * the /partner/remove-reviews demo.
 */
class CreateDemoPartner extends Command
{
    protected $signature = 'demo:create-partner {--fresh : Delete the existing demo partner (if any) and recreate everything}';

    protected $description = 'Create (or recreate) the seeded demo Partner account with the full 30-property eStay catalog.';

    private const PARTNER_PASSWORD = 'demo@1234';

    /**
     * One partner email per property type, keyed by the exact type name used in PROPERTIES/
     * ROOM_PLANS below. demo-partner@gmail.com (DemoAccounts::PARTNER_EMAIL) is the one public
     * identity with login-page autofill and delete/edit guarding — the other 5 are plain accounts
     * that exist only to hold their type's properties correctly.
     */
    private const PARTNER_TYPES = [
        'Hotel' => DemoAccounts::PARTNER_EMAIL,
        'Resort' => 'demo-partner-resort@gmail.com',
        'Apartment' => 'demo-partner-apartment@gmail.com',
        'Homestay' => 'demo-partner-homestay@gmail.com',
        'Villa' => 'demo-partner-villa@gmail.com',
        'Guest House' => 'demo-partner-guesthouse@gmail.com',
    ];

    /**
     * demo-partner@gmail.com keeps "Partner" (its existing public identity); the other 5 get a
     * type-specific last name so admin reports (Booking Report, Payout Report, etc.) can actually
     * tell them apart instead of every row saying the identical "Demo Partner".
     */
    private const PARTNER_LAST_NAMES = [
        'Hotel' => 'Partner',
        'Resort' => 'Resorts',
        'Apartment' => 'Apartments',
        'Homestay' => 'Homestays',
        'Villa' => 'Villas',
        'Guest House' => 'Guest House',
    ];

    /**
     * Country-default commission rate (%) — only applied via firstOrCreate() where a
     * CommissionRate doesn't already exist, so a real admin-configured rate is never overwritten.
     * All values stay well under 30: PropertyService::savePaymentConfig() throws if a property's
     * advance_percentage (randomly 30-80 here) falls below the effective commission rate, so
     * anything close to or above 30 risks crashing property creation on an unlucky roll.
     */
    private const COUNTRY_COMMISSION_RATES = [
        'India' => 15.00,
        'Nigeria' => 12.00,
        'United Arab Emirates' => 18.00,
        'United States' => 20.00,
    ];

    /**
     * Every demo partner gets a CommissionPartnerOverride this many points below their country's
     * effective default rate, in every country they operate in — a "negotiated rate," and it also
     * means every demo booking's commission_source is realistically 'partner_override' rather than
     * falling back to 'country_default'.
     */
    private const PARTNER_COMMISSION_DISCOUNT = 3.00;

    /**
     * Names of the partner-scope tax registration fields set up per country (via SQL, not this
     * command — matches whatever /registration-fields actually has configured). Used only to look
     * up the right field per country; if a country's field doesn't exist yet, that country is
     * silently skipped rather than erroring, since this command must not depend on admin-side
     * setup steps that happen outside it.
     */
    private const PARTNER_TAX_FIELD_NAMES = [
        'India' => 'GSTIN',
        'Nigeria' => 'TIN',
        'United Arab Emirates' => 'TRN',
        'United States' => 'EIN',
    ];

    /**
     * [name, country, city, property_type].
     */
    private const PROPERTIES = [
        ['Royal Orchid Grand', 'India', 'Mumbai', 'Hotel'],
        ['Royal Orchid Grand - Ahmedabad', 'India', 'Ahmedabad', 'Hotel'],
        ['Royal Orchid Grand - Goa', 'India', 'Goa', 'Resort'],
        ['Aravalli Heritage Stay', 'India', 'Udaipur', 'Hotel'],
        ['Palm Breeze Resort', 'India', 'Goa', 'Resort'],
        ['Urban Nest Suites', 'India', 'Bengaluru', 'Apartment'],
        ['Urban Nest Suites - Hyderabad', 'India', 'Hyderabad', 'Apartment'],
        ['Jaipur Haveli Retreat', 'India', 'Jaipur', 'Homestay'],
        ['Serenity Pool Villa', 'India', 'Lonavala', 'Villa'],
        ['The Mumbai Residency', 'India', 'Mumbai', 'Guest House'],
        ['Lagos Crown Hotel', 'Nigeria', 'Lagos', 'Hotel'],
        ['Lagos Crown Hotel - Abuja', 'Nigeria', 'Abuja', 'Hotel'],
        ['Atlantic Breeze Resort', 'Nigeria', 'Lagos', 'Resort'],
        ['Abuja Executive Suites', 'Nigeria', 'Abuja', 'Apartment'],
        ['Palm Grove Villa', 'Nigeria', 'Lekki', 'Villa'],
        ['Heritage House Enugu', 'Nigeria', 'Enugu', 'Homestay'],
        ['Calabar Garden Guest House', 'Nigeria', 'Calabar', 'Guest House'],
        ['Emirates Grand Stay', 'United Arab Emirates', 'Dubai', 'Hotel'],
        ['Emirates Grand Stay - Abu Dhabi', 'United Arab Emirates', 'Abu Dhabi', 'Hotel'],
        ['Marina Skyline Apartments', 'United Arab Emirates', 'Dubai', 'Apartment'],
        ['Desert Pearl Resort', 'United Arab Emirates', 'Ras Al Khaimah', 'Resort'],
        ['Palm Horizon Villa', 'United Arab Emirates', 'Dubai', 'Villa'],
        ['Creekside Boutique Hotel', 'United Arab Emirates', 'Dubai', 'Hotel'],
        ['Al Ain Garden Retreat', 'United Arab Emirates', 'Al Ain', 'Homestay'],
        ['Liberty Grand Hotel', 'United States', 'New York', 'Hotel'],
        ['Liberty Grand Hotel - Miami', 'United States', 'Miami', 'Hotel'],
        ['Pacific Sunset Resort', 'United States', 'San Diego', 'Resort'],
        ['Manhattan Urban Suites', 'United States', 'New York', 'Apartment'],
        ['Orlando Family Villa', 'United States', 'Orlando', 'Villa'],
        ['Boston Heritage House', 'United States', 'Boston', 'Guest House'],
    ];

    /**
     * One floor/room-type template per property type — [floor name, room type, count, first room number].
     */
    private const ROOM_PLANS = [
        'Hotel' => [
            ['floor' => 'First Floor', 'room_type' => 'Standard Room', 'count' => 6, 'start' => 101],
            ['floor' => 'Second Floor', 'room_type' => 'Deluxe Room', 'count' => 6, 'start' => 201],
            ['floor' => 'Third Floor', 'room_type' => 'Executive Suite', 'count' => 4, 'start' => 301],
            ['floor' => 'Fourth Floor', 'room_type' => 'Family Room', 'count' => 4, 'start' => 401],
            ['floor' => 'Fifth Floor', 'room_type' => 'Superior Double Room with Bathtub', 'count' => 2, 'start' => 501],
        ],
        'Resort' => [
            ['floor' => 'First Floor', 'room_type' => 'Deluxe Room', 'count' => 8, 'start' => 101],
        ],
        'Apartment' => [
            ['floor' => 'First Floor', 'room_type' => 'Executive Suite', 'count' => 6, 'start' => 101],
        ],
        'Homestay' => [
            ['floor' => 'First Floor', 'room_type' => 'Standard Room', 'count' => 4, 'start' => 101],
        ],
        'Villa' => [
            ['floor' => 'First Floor', 'room_type' => 'Family Room', 'count' => 3, 'start' => 101],
        ],
        'Guest House' => [
            ['floor' => 'First Floor', 'room_type' => 'Standard Room', 'count' => 5, 'start' => 101],
        ],
    ];

    private const ROOM_TYPE_DEFS = [
        'Standard Room' => ['bed_type' => 'Twin', 'max_guests' => 2, 'price' => 80.00, 'room_size' => '220 sq ft'],
        'Deluxe Room' => ['bed_type' => 'King', 'max_guests' => 2, 'price' => 150.00, 'room_size' => '300 sq ft'],
        'Executive Suite' => ['bed_type' => 'King', 'max_guests' => 3, 'price' => 320.00, 'room_size' => '550 sq ft'],
        'Family Room' => ['bed_type' => 'Double Queen', 'max_guests' => 4, 'price' => 200.00, 'room_size' => '420 sq ft'],
        'Superior Double Room with Bathtub' => ['bed_type' => 'Double', 'max_guests' => 2, 'price' => 250.00, 'room_size' => '260 sq ft'],
    ];

    /**
     * Graduated refund ladder for the partner's own ("custom") cancellation policy, one policy
     * per country (CancellationPolicyService scopes them that way).
     */
    private const CANCELLATION_TIERS = [
        ['days_before_checkin' => 0, 'refund_percentage' => 0],
        ['days_before_checkin' => 2, 'refund_percentage' => 50],
        ['days_before_checkin' => 3, 'refund_percentage' => 70],
        ['days_before_checkin' => 5, 'refund_percentage' => 100],
    ];

    /**
     * Rotated across properties with pets_allowed=true so the demo shows varied real-world pet
     * policies rather than one repeated sentence — free, one-time fee, per-night charge, and a
     * refundable deposit.
     */
    private const PET_POLICY_TEMPLATES = [
        'Pets are welcome at no additional charge. Maximum 2 pets per room.',
        'Pets allowed with a one-time cleaning fee of $25 per stay.',
        'Pets allowed for an additional $15 per night.',
        'Pets welcome with a refundable pet deposit of $50, charged at check-in.',
    ];

    /**
     * A handful of smaller towns from the document that aren't in the ref_* reference pool at
     * all (verified — not even under a slightly different spelling), so ensureCity() below has
     * nothing to auto-provision from. Real-world coordinates, keyed by [country => [city => [lat,
     * lng, state name]]] — the state is expected to already exist operationally by the time this
     * runs (created earlier in the same command run from a ref-pool city in the same state).
     */
    private const MANUAL_CITY_FALLBACKS = [
        'India' => [
            'Lonavala' => ['lat' => 18.7546, 'lng' => 73.4062, 'state' => 'Maharashtra', 'timezone' => 'Asia/Kolkata'],
        ],
    ];

    /**
     * Real postal codes per city, keyed by [country => [city => zip]] — UAE is deliberately
     * absent: it has no street-address postal code system at all (addresses there use PO boxes),
     * so fabricating a plausible-looking zip for it would be less honest than leaving it blank.
     */
    private const ZIP_CODES = [
        'India' => [
            'Mumbai' => '400001',
            'Ahmedabad' => '380001',
            'Goa' => '403001',
            'Udaipur' => '313001',
            'Bengaluru' => '560001',
            'Hyderabad' => '500001',
            'Jaipur' => '302001',
            'Lonavala' => '410401',
        ],
        'Nigeria' => [
            'Lagos' => '100001',
            'Abuja' => '900001',
            'Lekki' => '106104',
            'Enugu' => '400001',
            'Calabar' => '540001',
        ],
        'United States' => [
            'New York' => '10001',
            'Miami' => '33101',
            'San Diego' => '92101',
            'Orlando' => '32801',
            'Boston' => '02101',
        ],
    ];

    private const CUSTOMER_PASSWORD = '12345678';

    /**
     * Real, usable guest logins (not just decorative rows) — the team logs into these later to
     * set profile pictures, per explicit instruction, so they're created with firstOrCreate and
     * never touched again by --fresh: a customer's own profile customization must survive the
     * partner catalog being reset. Names picked to fit the demo's 4 countries.
     *
     * [first_name, last_name, email, country].
     */
    private const DEMO_CUSTOMERS = [
        ['Rakesh', 'Sharma', 'rakesh@gmail.com', 'India'],
        ['Priya', 'Patel', 'priya@gmail.com', 'India'],
        ['Chidi', 'Okafor', 'chidi@gmail.com', 'Nigeria'],
        ['Ahmed', 'Al Mansoori', 'ahmed@gmail.com', 'United Arab Emirates'],
        ['Peter', 'Johnson', 'peter@gmail.com', 'United States'],
    ];

    /**
     * Per property: 2 upcoming (confirmed), 1 currently checked in, 3 completed (past — each
     * gets a review), 1 cancelled. Gives every booking-status filter something to show without
     * needing a dedicated pass per status.
     */
    private const BOOKING_PLAN = [
        ['status' => BookingStatus::Confirmed, 'dayOffsetMin' => 1, 'dayOffsetMax' => 20, 'nightsMin' => 1, 'nightsMax' => 4],
        ['status' => BookingStatus::Confirmed, 'dayOffsetMin' => 1, 'dayOffsetMax' => 20, 'nightsMin' => 1, 'nightsMax' => 4],
        // Never 0 (today): wallet:credit-checked-in-bookings correctly withholds credit until
        // the property's local check-in time has passed, and US properties are hours behind —
        // a "today" checked-in booking could sit uncredited for hours depending on what time the
        // seed command happens to run. -1/-2 guarantees credit-eligibility on the very first run.
        ['status' => BookingStatus::CheckedIn, 'dayOffsetMin' => -2, 'dayOffsetMax' => -1, 'nightsMin' => 1, 'nightsMax' => 3],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -45, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -45, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -45, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Cancelled, 'dayOffsetMin' => -30, 'dayOffsetMax' => -1, 'nightsMin' => 1, 'nightsMax' => 3],
    ];

    /**
     * Richer plan for demo-partner@gmail.com only (Hotel) — the one public-facing identity with
     * login-page autofill, so it needs to look like a real, established, actively-operating
     * partner rather than a thin seed. 18 bookings/property (vs BOOKING_PLAN's 7): 4 upcoming,
     * 1 checked-in, 9 completed (each gets a review — this is where "more reviews" comes from)
     * spread across up to 6 months of history rather than 45 days, 2 cancelled, plus two
     * genuinely-reachable statuses BOOKING_PLAN never uses — Expired (BookingMaintenanceService
     * really does expire an unpaid PendingPayment booking after its payment window) and
     * PendingPayment itself (a booking currently "in flight"). Deliberately NOT using
     * BookingStatus::Pending — confirmed dead code elsewhere in this app (no path ever sets it),
     * so a demo booking sitting in that state would be a state that literally cannot occur for
     * real.
     */
    private const HOTEL_BOOKING_PLAN = [
        ['status' => BookingStatus::Confirmed, 'dayOffsetMin' => 1, 'dayOffsetMax' => 30, 'nightsMin' => 1, 'nightsMax' => 4],
        ['status' => BookingStatus::Confirmed, 'dayOffsetMin' => 1, 'dayOffsetMax' => 30, 'nightsMin' => 1, 'nightsMax' => 4],
        ['status' => BookingStatus::Confirmed, 'dayOffsetMin' => 1, 'dayOffsetMax' => 30, 'nightsMin' => 1, 'nightsMax' => 4],
        ['status' => BookingStatus::Confirmed, 'dayOffsetMin' => 1, 'dayOffsetMax' => 30, 'nightsMin' => 1, 'nightsMax' => 4],
        ['status' => BookingStatus::CheckedIn, 'dayOffsetMin' => -2, 'dayOffsetMax' => -1, 'nightsMin' => 1, 'nightsMax' => 3],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -180, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -180, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -180, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -180, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -180, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -180, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -180, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -180, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Completed, 'dayOffsetMin' => -180, 'dayOffsetMax' => -3, 'nightsMin' => 1, 'nightsMax' => 5],
        ['status' => BookingStatus::Cancelled, 'dayOffsetMin' => -90, 'dayOffsetMax' => -1, 'nightsMin' => 1, 'nightsMax' => 3],
        ['status' => BookingStatus::Cancelled, 'dayOffsetMin' => -90, 'dayOffsetMax' => -1, 'nightsMin' => 1, 'nightsMax' => 3],
        // Abandoned checkout — never paid, so no payment/room-assignment/inventory row (matches
        // real behavior: none of those tables get touched for a status this plan excludes).
        ['status' => BookingStatus::Expired, 'dayOffsetMin' => 5, 'dayOffsetMax' => 30, 'nightsMin' => 1, 'nightsMax' => 3],
        // Currently "in flight" — awaiting payment, same no-payment/no-assignment treatment.
        ['status' => BookingStatus::PendingPayment, 'dayOffsetMin' => 3, 'dayOffsetMax' => 14, 'nightsMin' => 1, 'nightsMax' => 3],
    ];

    private const REVIEW_SAMPLES = [
        ['rating' => 5.0, 'text' => 'Absolutely wonderful stay! The staff were incredibly attentive and the room was spotless. Will definitely book again.'],
        ['rating' => 4.5, 'text' => 'Great location and comfortable beds. Breakfast could have had more variety, but overall a solid experience.'],
        ['rating' => 5.0, 'text' => 'Exceeded our expectations. Check-in was smooth and the room had a beautiful view. Highly recommend!'],
        ['rating' => 4.0, 'text' => 'Good value for money. The room was clean and quiet. A bit far from the main attractions but easy to get a cab.'],
        ['rating' => 4.5, 'text' => 'Loved the amenities and the friendly staff. The pool area was a nice touch for our evening relaxation.'],
        ['rating' => 3.5, 'text' => 'Decent stay overall. The AC was a little noisy at night, but the bed was comfortable and staff resolved our request quickly.'],
        ['rating' => 5.0, 'text' => 'Perfect for a family trip. Spacious room, helpful concierge, and the breakfast spread was fantastic.'],
    ];

    /**
     * Low-rating, obviously-fake-looking reviews for the /partner/remove-reviews demo — paired
     * 1:1 with REMOVAL_REASONS below by index (spam review → "Spam / Fake Review" reason, etc.).
     * 2 of these get created per property, each needing its own completed booking (a booking can
     * only carry one review), separate from the 3 legitimate reviews each property already has.
     */
    private const FAKE_REVIEW_TEMPLATES = [
        ['rating' => 1.0, 'text' => 'Worst hotel ever!!! Scam artists, DO NOT BOOK. They stole my money and the room was disgusting. AVOID AVOID AVOID!!!'],
        ['rating' => 1.0, 'text' => 'fake place dont trust. very bad bad bad. for real reviews check www.totally-a-scam-site.example'],
        ['rating' => 1.5, 'text' => 'This review has nothing to do with my actual stay lol just testing this out. Anyway 1 star because why not.'],
        ['rating' => 2.0, 'text' => 'Rude staff, dirty rooms, and they charged me twice for nothing! My lawyer will be in touch. Everyone should know about this before booking!!!'],
    ];

    private const REMOVAL_REASONS = [
        ['reason' => 'Abusive Language', 'description' => "This review contains offensive language and doesn't reflect an actual stay at our property."],
        ['reason' => 'Spam / Fake Review', 'description' => 'We believe this review is spam or was posted by a bot — it references an unrelated website and has no connection to a real booking.'],
        ['reason' => 'Irrelevant Content', 'description' => 'The content of this review has nothing to do with our property or service.'],
        ['reason' => 'Defamatory Content', 'description' => 'This review makes false and damaging claims about our business that we can prove are untrue.'],
    ];

    /**
     * Exact counts (not pure random), same ~80/15/5 split as before, recalibrated for the new
     * total: 4 fake reviews × 10 Hotel properties + 2 × 20 other-type properties = 80 total.
     */
    private const REMOVAL_STATUS_COUNTS = [
        'approved' => 64,
        'rejected' => 12,
        'requested' => 4,
    ];

    /**
     * Regional payment-gateway preference, for realism only — must NOT be Manual: Commission
     * Service::resolveOnlineCollectedAmount() deliberately excludes Manual-gateway payments from
     * wallet-credit calculations (that's "money the property collected directly," not something
     * the platform ever touched), so a Manual payment here would zero out the wallet credits that
     * already work today.
     */
    private const PAYMENT_GATEWAY_BY_COUNTRY = [
        'India' => PaymentGateway::Razorpay,
        'Nigeria' => PaymentGateway::Flutterwave,
        'United Arab Emirates' => PaymentGateway::Stripe,
        'United States' => PaymentGateway::Stripe,
    ];

    /**
     * Exact counts (not pure random) for the 30 withdrawal requests, same reasoning as
     * REMOVAL_STATUS_COUNTS: 21 approved (real wallet debit via PropertyWalletService::
     * approveWithdrawal()), 6 left pending for the admin queue, 3 rejected.
     */
    private const WITHDRAWAL_STATUS_COUNTS = [
        'approved' => 21,
        'pending' => 6,
        'rejected' => 3,
    ];

    private const CANCELLATION_REASONS = [
        'Change of travel plans.',
        'Found a better option elsewhere.',
        'Booked by mistake.',
        'Family emergency.',
        'No longer able to travel on these dates.',
    ];

    public function handle(): int
    {
        if (! SystemMode::isMulti()) {
            $this->error('This app is running in single mode — refusing to create a demo partner. This command is multi-mode only, to avoid creating fake accounts/data in a single-mode production database.');

            return self::FAILURE;
        }

        $primaryEmail = DemoAccounts::PARTNER_EMAIL;
        $existing = User::where('email', $primaryEmail)->first();

        if ($existing) {
            if (! $this->option('fresh')) {
                $this->info("Demo partners already exist ({$primaryEmail} and its per-type siblings). Re-run with --fresh to delete and recreate everything.");

                return self::SUCCESS;
            }

            $this->warn('--fresh: deleting existing demo partners (one per property type) and their data...');
            $this->deleteExistingDemoPartners();
        }

        $countryNames = collect(self::PROPERTIES)->pluck(1)->unique();
        $countries = Country::whereIn('name', $countryNames)->get()->keyBy('name');
        $missingCountries = $countryNames->diff($countries->keys());

        if ($missingCountries->isNotEmpty()) {
            $this->error('Missing active countries (enable in System Settings first): '.$missingCountries->implode(', '));

            return self::FAILURE;
        }

        $typeNames = collect(self::PROPERTIES)->pluck(3)->unique();
        $propertyTypes = PropertyType::whereIn('name', $typeNames)->get()->keyBy('name');
        $missingTypes = $typeNames->diff($propertyTypes->keys());

        if ($missingTypes->isNotEmpty()) {
            $this->error('Missing property types (create in System Settings first): '.$missingTypes->implode(', '));

            return self::FAILURE;
        }

        // Must happen before ANY property's payment config is saved (completePropertySetup() below
        // calls savePaymentConfig(), which throws if advance_percentage < the effective commission
        // rate) — setting this up first, once, guarantees every property this run creates sees a
        // real rate already in place rather than the 0% fallback.
        $commissionRatesCreated = $this->ensureCountryCommissionRates($countries);

        $propertiesByType = collect(self::PROPERTIES)->groupBy(3);

        $propertyService = app(PropertyService::class);
        $facilityIds = Facility::where('status', 'active')->limit(6)->pluck('id')->toArray();
        $generateRooms = app(GenerateRoomsAction::class);

        $created = collect();
        $createdProperties = collect();
        $totalRooms = 0;
        $partnerSummaries = collect();
        $overridesCreated = 0;
        $taxFieldsFilled = 0;

        foreach (self::PARTNER_TYPES as $typeName => $email) {
            $propertiesForType = $propertiesByType->get($typeName, collect());

            if ($propertiesForType->isEmpty()) {
                continue;
            }

            // Each partner is scoped to only the countries their OWN properties are actually in
            // (Homestay has no US property, Guest House has no UAE one) — syncing all 4 countries
            // onto every partner regardless would be the same "we didn't check" mistake that led
            // to the one-partner-six-types design in the first place.
            $typeCountryNames = $propertiesForType->pluck(1)->unique();
            // NOT $countries->only($typeCountryNames->all()): Collection::only() on an Eloquent
            // Collection filters by primary key (id), ignoring the keyBy('name') re-keying —
            // silently returned an empty collection every time, so no country beyond the one
            // CreatePartnerAction syncs from country_id ever made it onto the partner.
            $typeCountries = $countries->filter(fn (Country $country, string $name) => $typeCountryNames->contains($name));
            $homeCountry = $countries[$propertiesForType->first()[1]];
            $homeCity = $this->ensureCity($homeCountry, $propertiesForType->first()[2]);

            $partner = $this->createPartner($email, self::PARTNER_LAST_NAMES[$typeName], $propertyTypes[$typeName], $homeCountry, $homeCity, $typeCountries);
            $overridesCreated += $this->ensurePartnerCommissionOverrides($partner, $propertyTypes[$typeName], $typeCountries);
            $taxFieldsFilled += $this->fillPartnerTaxRegistrationValues($partner, $typeCountries);
            $roomTypesByName = $this->createRoomTypes($partner, $typeName);
            $this->createCancellationPolicies($partner, $typeCountries);
            $adminDefaultAvailable = $this->resolveAdminDefaultAvailability($partner, $typeCountries);

            $typeCreatedCount = 0;

            foreach ($propertiesForType as [$name, $countryName, $cityName, $propTypeName]) {
                $country = $countries[$countryName];
                $city = $this->ensureCity($country, $cityName);

                if (! $city) {
                    $this->warn("Skipping \"{$name}\" — city \"{$cityName}\" not found under {$countryName} (not in the reference location pool either).");

                    continue;
                }

                $displayName = str_contains(mb_strtolower($name), mb_strtolower($cityName))
                    ? $name
                    : $name.' - '.$cityName;

                $property = $this->createProperty($partner, $country, $city, $propertyTypes[$propTypeName], $displayName, count(self::ROOM_PLANS[$propTypeName]));
                $roomCount = $this->buildRoomsForProperty($property, $propTypeName, $roomTypesByName, $generateRooms);
                $canUseAdminDefault = $adminDefaultAvailable[$countryName] ?? false;
                $this->completePropertySetup($property, $propertyService, $facilityIds, $canUseAdminDefault);

                $created->push($displayName);
                $createdProperties->push($property);
                $totalRooms += $roomCount;
                $typeCreatedCount++;
            }

            $partnerSummaries->push(['type' => $typeName, 'email' => $email, 'properties' => $typeCreatedCount]);
        }

        $hotelPropertyTypeId = $propertyTypes['Hotel']->id;
        $customers = $this->createDemoCustomers($countries);
        [$bookingCount, $reviewCount] = $this->createDemoBookingsAndReviews($createdProperties, $customers, $hotelPropertyTypeId);
        $fakeReviewCount = $this->createFakeReviewsWithRemovalRequests($createdProperties, $customers, $hotelPropertyTypeId);

        // Payments/refunds must exist BEFORE wallet crediting: CommissionService's real formula
        // (via resolveOnlineCollectedAmount()) only kicks in once a non-Manual payment row exists —
        // without one it falls back to "assume full total collected," which works but isn't the
        // real code path. Creating these first makes crediting use the actual formula, not the
        // fallback made for exactly this kind of gap.
        [$paymentCount, $refundCount] = $this->createPaymentsAndRefunds($createdProperties);
        $assignedCount = $this->assignRoomsToBookings($createdProperties);
        $inventoryCount = $this->reserveRoomInventory($createdProperties);

        $creditedCount = $this->creditWalletsForEligibleBookings();
        // No Partner argument needed — every property already carries its own owning partner via
        // property->partner, resolved per-iteration, since $createdProperties now spans all 6.
        $withdrawalCount = $this->requestSampleWithdrawals($createdProperties);

        foreach ($partnerSummaries as $summary) {
            $this->info("{$summary['type']} partner ready: {$summary['email']} / ".self::PARTNER_PASSWORD." ({$summary['properties']} properties)");
        }
        $this->info("Country commission rates created: {$commissionRatesCreated} (existing ones left untouched)");
        $this->info("Partner commission overrides created: {$overridesCreated}");
        $this->info("Partner tax registration values filled: {$taxFieldsFilled} (0 if /registration-fields SQL hasn't been run yet)");
        $this->info('Properties created: '.$created->count().'/'.count(self::PROPERTIES));
        $this->info("Physical rooms created: {$totalRooms}");
        $this->info('Demo customers ready: '.$customers->pluck('email')->implode(', ').' (password: '.self::CUSTOMER_PASSWORD.')');
        $this->info("Bookings created: {$bookingCount}, reviews created: {$reviewCount}");
        $this->info("Fake reviews with removal requests: {$fakeReviewCount}");
        $this->info("Payments created: {$paymentCount}, refunds created: {$refundCount}");
        $this->info("Rooms auto-assigned: {$assignedCount}");
        $this->info("Room-inventory dates reserved: {$inventoryCount}");
        $this->info("Wallet credits: {$creditedCount}, withdrawal requests: {$withdrawalCount}");

        return self::SUCCESS;
    }

    /**
     * Finds an operational City under $country by name, auto-provisioning it (State + City)
     * from the read-only ref_* location pool if it doesn't exist yet — the live target server
     * has no terminal access to fix "city not found" gaps interactively, so this command has
     * to be able to self-heal them.
     */
    private function ensureCity(Country $country, string $cityName): ?City
    {
        $city = City::where('country_id', $country->id)->where('name', $cityName)->first();

        if ($city) {
            return $city;
        }

        if (! $country->ref_country_id) {
            return null;
        }

        // orderByDesc('population') matters: countries like the US have many small towns
        // sharing a name with a major city (e.g. "Miami" also exists in Kansas, Indiana, Ohio,
        // Oklahoma, Arizona, Texas — an unordered ->first() picked Miami, Kansas in an earlier
        // run, giving that property the wrong state, coordinates, AND timezone). Highest
        // population is a reliable proxy for "the city the document actually means."
        $refCity = RefCity::whereHas('state', fn ($q) => $q->where('country_id', $country->ref_country_id))
            ->where('name', $cityName)
            ->orderByDesc('population')
            ->first();

        // Reference pool sometimes suffixes names differently (e.g. "Al Ain City" vs "Al Ain",
        // "New York City" vs "New York").
        if (! $refCity) {
            $refCity = RefCity::whereHas('state', fn ($q) => $q->where('country_id', $country->ref_country_id))
                ->where('name', 'like', $cityName.'%')
                ->orderByDesc('population')
                ->first();
        }

        if (! $refCity) {
            return $this->ensureCityFromManualFallback($country, $cityName);
        }

        $refState = $refCity->state;

        $state = State::firstOrCreate(
            ['ref_state_id' => $refState->id],
            [
                'country_id' => $country->id,
                'name' => $refState->name,
                'latitude' => $refState->latitude,
                'longitude' => $refState->longitude,
                'is_active' => true,
            ]
        );

        return City::create([
            'ref_city_id' => $refCity->id,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'name' => $cityName,
            'latitude' => $refCity->latitude,
            'longitude' => $refCity->longitude,
            'status' => CityStatus::Active,
        ]);
    }

    /**
     * ref_city_id is nullable specifically to allow entries like this — a real place with no
     * matching row in the reference pool. Relies on the named state already existing
     * operationally (created earlier in this same run from a ref-pool city in that state).
     */
    private function ensureCityFromManualFallback(Country $country, string $cityName): ?City
    {
        $fallback = self::MANUAL_CITY_FALLBACKS[$country->name][$cityName] ?? null;

        if (! $fallback) {
            return null;
        }

        $state = State::where('country_id', $country->id)->where('name', $fallback['state'])->first();

        if (! $state) {
            return null;
        }

        return City::create([
            'ref_city_id' => null,
            'country_id' => $country->id,
            'state_id' => $state->id,
            'name' => $cityName,
            'latitude' => $fallback['lat'],
            'longitude' => $fallback['lng'],
            'status' => CityStatus::Active,
        ]);
    }

    /**
     * @param  Collection<string, Country>  $countries  the countries THIS partner's own properties
     *                                                  are in — not all 4 globally
     */
    private function createPartner(string $email, string $lastName, PropertyType $propertyType, Country $homeCountry, ?City $homeCity, $countries): Partner
    {
        $partner = app(CreatePartnerAction::class)->handle([
            'first_name' => 'Demo',
            'last_name' => $lastName,
            'email' => $email,
            'password' => Hash::make(self::PARTNER_PASSWORD),
            'phone' => (string) random_int(6000000000, 9999999999),
            'dial_code' => '+'.ltrim((string) $homeCountry->phone_code, '+'),
            'address' => 'Business District, '.($homeCity?->name ?? $homeCountry->name),
            'country' => $homeCountry->name,
            'state' => $homeCity?->state?->name,
            'zip_code' => self::ZIP_CODES[$homeCountry->name][$homeCity?->name ?? ''] ?? null,
            'country_id' => $homeCountry->id,
            'city' => $homeCity?->name ?? 'Demo City',
            'property_type_id' => $propertyType->id,
        ]);

        $partner->update([
            'verification_status' => PartnerVerificationStatus::Approved,
            'verified_at' => now(),
        ]);

        foreach ($countries as $country) {
            $partner->countries()->syncWithoutDetaching([$country->id]);
        }

        return $partner;
    }

    /**
     * One country-default CommissionRate per country in COUNTRY_COMMISSION_RATES — firstOrCreate,
     * so a real admin-configured rate for a country is never touched. Must run before any
     * property's payment config is saved (see the note at its call site in handle()).
     *
     * @param  Collection<string, Country>  $countries
     */
    private function ensureCountryCommissionRates(Collection $countries): int
    {
        $created = 0;

        foreach ($countries as $country) {
            $rate = self::COUNTRY_COMMISSION_RATES[$country->name] ?? null;

            if ($rate === null) {
                continue;
            }

            $row = CommissionRate::firstOrCreate(
                ['country_id' => $country->id, 'property_type_id' => null],
                ['rate' => $rate],
            );

            if ($row->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * One CommissionPartnerOverride per (partner, country) they operate in, priced
     * PARTNER_COMMISSION_DISCOUNT points below whichever rate is actually effective for that
     * country right now (the just-created default, or a pre-existing admin rate — never our own
     * hardcoded constant directly, so this stays correct even if an admin's real rate differs from
     * COUNTRY_COMMISSION_RATES). Skips a country with no resolvable rate at all rather than
     * fabricating a negative or arbitrary override.
     *
     * @param  Collection<string, Country>  $countries
     */
    private function ensurePartnerCommissionOverrides(Partner $partner, PropertyType $propertyType, Collection $countries): int
    {
        $created = 0;

        foreach ($countries as $country) {
            $countryRate = CommissionRate::where('country_id', $country->id)
                ->whereNull('property_type_id')
                ->value('rate');

            if ($countryRate === null) {
                continue;
            }

            $overrideRate = max(0.0, (float) $countryRate - self::PARTNER_COMMISSION_DISCOUNT);

            $row = CommissionPartnerOverride::firstOrCreate(
                ['country_id' => $country->id, 'partner_id' => $partner->id, 'property_type_id' => $propertyType->id],
                ['rate' => $overrideRate, 'description' => 'Demo negotiated rate: '.$countryRate.'% country default minus '.self::PARTNER_COMMISSION_DISCOUNT.' points.'],
            );

            if ($row->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Fills the partner-scope tax registration field (GSTIN/TIN/TRN/EIN, set up separately via
     * SQL against /registration-fields — not by this command) for every country this partner
     * actually operates in, using the same PartnerRegistrationValue::updateOrCreate() pattern
     * PartnerProfileManage itself writes with directly (there is no dedicated service method for
     * this). Silently skips any country whose field doesn't exist yet, so this command never
     * hard-depends on that SQL having already been run.
     *
     * @param  Collection<string, Country>  $countries
     */
    private function fillPartnerTaxRegistrationValues(Partner $partner, Collection $countries): int
    {
        $filled = 0;

        foreach ($countries as $country) {
            $fieldName = self::PARTNER_TAX_FIELD_NAMES[$country->name] ?? null;

            if ($fieldName === null) {
                continue;
            }

            $field = RegistrationField::where('scope', RegistrationFieldScope::Partner)
                ->where('country_id', $country->id)
                ->where('name', $fieldName)
                ->where('status', 'active')
                ->first();

            if (! $field) {
                continue;
            }

            PartnerRegistrationValue::updateOrCreate(
                ['partner_id' => $partner->id, 'registration_field_id' => $field->id],
                ['value' => [$this->generateTaxId($country->name)]],
            );

            $filled++;
        }

        return $filled;
    }

    /**
     * Plausible-looking tax ID per country's real format — not a valid checksum, just the right
     * shape (GSTIN's 15 chars, Nigeria TIN's 8-digit+branch-code, UAE TRN's 15 digits, US EIN's
     * 2-7 digit split).
     */
    private function generateTaxId(string $countryName): string
    {
        return match ($countryName) {
            'India' => str_pad((string) random_int(10, 37), 2, '0', STR_PAD_LEFT)
                .collect(range(1, 5))->map(fn () => chr(random_int(65, 90)))->implode('')
                .str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT)
                .chr(random_int(65, 90)).'1Z'.chr(random_int(65, 90)),
            'Nigeria' => str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT).'-0001',
            'United Arab Emirates' => '100'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT).'003',
            'United States' => str_pad((string) random_int(0, 99), 2, '0', STR_PAD_LEFT).'-'.str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
            default => (string) random_int(100000000, 999999999),
        };
    }

    /**
     * Scoped to only the room-type names $typeName's own ROOM_PLANS entry actually references
     * (e.g. a Villa partner only ever needs "Family Room") — creating all 5 defs for every
     * partner regardless would leave unused room-type rows cluttering partners who never have a
     * room of that type.
     *
     * @return array<string, RoomType>
     */
    private function createRoomTypes(Partner $partner, string $typeName): array
    {
        $roomTypeService = app(RoomTypeService::class);
        $roomTypesByName = [];
        $neededNames = collect(self::ROOM_PLANS[$typeName])->pluck('room_type')->unique();

        foreach ($neededNames as $name) {
            $def = self::ROOM_TYPE_DEFS[$name];
            $existing = RoomType::where('partner_id', $partner->id)->where('name', $name)->first();

            $roomTypesByName[$name] = $existing ?? $roomTypeService->createRoomType(
                data: [
                    'partner_id' => $partner->id,
                    'name' => $name,
                    'description' => '<p>A comfortable '.strtolower($name).' with all essential amenities for a pleasant stay.</p>',
                    'bed_type' => $def['bed_type'],
                    'max_guests' => $def['max_guests'],
                    'status' => RoomTypeStatus::Active,
                ],
                images: ['images/lorempic.svg'],
            );
        }

        return $roomTypesByName;
    }

    /**
     * The partner's own ("custom") cancellation policy, one per country — always safe to select
     * regardless of what admin has configured, since it's fully under this command's control.
     *
     * @param  Collection<string, Country>  $countries
     */
    private function createCancellationPolicies(Partner $partner, $countries): void
    {
        $service = app(CancellationPolicyService::class);

        foreach ($countries as $country) {
            $policy = $service->getActivePolicyForPartner($partner, $country->id);

            foreach (self::CANCELLATION_TIERS as $tier) {
                $service->saveRule($policy, $tier);
            }
        }
    }

    /**
     * "Admin Default Policy" is only a safe choice for a property if a real admin-authored policy
     * with at least one rule already exists for (country, partner's own property_type_id) — the
     * partner wizard's own fillStep5() silently falls back to "custom" otherwise, so setting
     * admin_default without this check would just get quietly overridden the moment someone opens
     * the property in the UI. This command never creates admin-level policies itself (that's
     * genuinely admin-curated content, out of scope here) — it only checks what already exists.
     *
     * @param  Collection<string, Country>  $countries
     * @return array<string, bool> keyed by country name
     */
    private function resolveAdminDefaultAvailability(Partner $partner, $countries): array
    {
        $service = app(CancellationPolicyService::class);
        $availability = [];

        foreach ($countries as $country) {
            $adminPolicy = $service->getAdminDefaultPolicy($country->id, $partner->property_type_id);
            $availability[$country->name] = $adminPolicy->rules()->exists();
        }

        return $availability;
    }

    private function createProperty(Partner $partner, Country $country, City $city, PropertyType $propertyType, string $name, int $totalFloors): Property
    {
        return Property::firstOrCreate(
            ['name' => $name, 'partner_id' => $partner->id],
            [
                'country_id' => $country->id,
                'property_type_id' => $propertyType->id,
                'ref_state_id' => $city->state?->ref_state_id,
                'ref_city_id' => $city->ref_city_id,
                'description' => '<p>'.$name.' offers a refined stay with elegant rooms, modern amenities, and warm local hospitality in the heart of '.$city->name.'.</p>',
                'meta_title' => $name,
                'total_floors' => $totalFloors,
                'street_address' => 'City Center, '.$city->name,
                'phone' => '1234567890',
                'dial_code' => '+'.ltrim((string) $country->phone_code, '+'),
                'email' => 'contact@'.Str::slug($name).'.example.com',
                'zip_code' => self::ZIP_CODES[$country->name][$city->name] ?? null,
                'timezone' => $this->resolveTimezone($city, $country),
                'check_in_time' => '14:00',
                'check_out_time' => '11:00',
                'status' => 'active',
                'latitude' => $city->latitude,
                'longitude' => $city->longitude,
                'bank_account_holder' => 'Demo Partner',
                'bank_name' => 'Demo Bank',
                'bank_account_number' => '0000123456',
                'bank_code' => 'DEMOUS33',
            ]
        );
    }

    /**
     * ref_cities carries the timezone; the operational City row only stores ref_city_id, so it's
     * resolved via that relationship. Falls back to MANUAL_CITY_FALLBACKS for cities with no
     * ref_city_id at all (e.g. Lonavala).
     */
    private function resolveTimezone(City $city, Country $country): ?string
    {
        if ($city->ref_city_id && $city->refCity) {
            return $city->refCity->timezone;
        }

        return self::MANUAL_CITY_FALLBACKS[$country->name][$city->name]['timezone'] ?? null;
    }

    /**
     * @param  array<string, RoomType>  $roomTypesByName
     */
    private function buildRoomsForProperty(Property $property, string $typeName, array $roomTypesByName, GenerateRoomsAction $generateRooms): int
    {
        $plan = self::ROOM_PLANS[$typeName];
        $totalRooms = 0;

        foreach ($plan as $sortOrder => $entry) {
            $roomType = $roomTypesByName[$entry['room_type']];
            $priceDef = self::ROOM_TYPE_DEFS[$entry['room_type']];

            $propertyRoom = PropertyRoom::firstOrCreate(
                ['property_id' => $property->id, 'room_type_id' => $roomType->id],
                [
                    'total_rooms' => $entry['count'],
                    'room_size' => $priceDef['room_size'],
                    'base_price_per_night' => $priceDef['price'],
                ]
            );

            $floor = Floor::firstOrCreate(
                ['property_id' => $property->id, 'name' => $entry['floor']],
                ['sort_order' => $sortOrder],
            );

            $generateRooms->handle([
                'floor_id' => $floor->id,
                'property_room_id' => $propertyRoom->id,
                'count' => $entry['count'],
                'start_number' => $entry['start'],
            ]);

            $totalRooms += $entry['count'];
        }

        return $totalRooms;
    }

    private function completePropertySetup(Property $property, PropertyService $service, array $facilityIds, bool $canUseAdminDefault): void
    {
        if (! empty($facilityIds)) {
            $service->syncFacilities($property, $facilityIds);
        }

        $service->completeRoomsStep($property);

        // applicableTo(), not an unscoped fetch: PropertyRule::applicableTo() is the real scope
        // every property-facing page (PartnerPropertyCreate, PropertyCreate, PropertyView...)
        // uses — NULL on country_id or property_type_id independently means "applies to all". An
        // unscoped fetch would apply every rule to every property regardless of scoping.
        $applicableRules = PropertyRule::applicableTo($property->country_id, $property->property_type_id)
            ->where('status', PropertyRuleStatus::Active)
            ->with('questions')
            ->get();

        $answers = [];
        foreach ($applicableRules as $rule) {
            foreach ($rule->questions as $question) {
                // Randomized rather than "always yes / always the first option" — real answers
                // vary property to property, and hardcoding weights to specific question wording
                // would silently degrade to this same fallback the moment someone edits a
                // question's text via the real admin UI.
                $options = collect($question->options ?? []);

                $answers[$question->id] = match ($question->answer_type) {
                    AnswerType::YesNo => random_int(1, 100) <= 70,
                    AnswerType::SingleSelect => $options->isNotEmpty() ? $options->random()['id'] : 'yes',
                    AnswerType::MultipleSelect => $options->isNotEmpty()
                        ? $options->random(random_int(1, $options->count()))->pluck('id')->all()
                        : ['yes'],
                };
            }
        }

        // ~80% pets allowed, per explicit instruction — varied policy text rotated in for those,
        // left null (nothing to say) when pets aren't allowed.
        $petsAllowed = random_int(1, 100) <= 80;
        $petPolicyDetails = $petsAllowed
            ? self::PET_POLICY_TEMPLATES[$property->id % count(self::PET_POLICY_TEMPLATES)]
            : null;

        $service->saveRuleAnswers($property, [
            'check_in_time' => '14:00',
            'check_out_time' => '11:00',
            'pets_allowed' => $petsAllowed,
            'pet_policy_details' => $petPolicyDetails,
            'custom_rules' => 'No smoking inside the premises. Noise curfew after 10 PM.',
            'answers' => $answers,
        ]);

        // Mirrors PartnerPropertyCreate::saveStep5() exactly (direct attribute update — the
        // actual policy content lives in CancellationPolicyService, this column just records
        // which one the property uses). Only offer admin_default where a real admin policy with
        // rules exists for this country; otherwise the wizard would silently flip it to custom
        // the moment someone opens this property, per its own fallback logic.
        $source = $canUseAdminDefault && random_int(1, 100) <= 50
            ? PropertyCancellationPolicySource::AdminDefault
            : PropertyCancellationPolicySource::Custom;

        $property->update(['cancellation_policy_source' => $source]);
        $service->completeCancellationPolicyStep($property);

        $service->savePaymentConfig($property, [
            'pay_at_property' => true,
            'advance_percentage' => (string) random_int(30, 80),
        ]);

        $service->saveImages($property, array_fill(0, 5, 'images/lorempic.svg'), [], null);

        // applicableTo(), not a bare property_type_id match: the real property-creation flow
        // (PropertyCreate's Legal & Compliance step) treats property_type_id IS NULL as "applies
        // to every type in this country" — a naive exact match would silently skip any
        // country-wide field.
        $regFields = RegistrationField::applicableTo($property->country_id, $property->property_type_id)
            ->where('status', 'active')
            ->get();

        $regValues = [];
        foreach ($regFields as $field) {
            $regValues[$field->id] = match ($field->field_type) {
                RegistrationFieldType::NumberInput => 12345,
                RegistrationFieldType::TextField => 'Demo Value',
                RegistrationFieldType::TextArea => 'Demo registration information for this property.',
                RegistrationFieldType::Checkboxes => [$field->options[0] ?? 'Option 1'],
                RegistrationFieldType::Date => now()->format('Y-m-d'),
                RegistrationFieldType::Dropdown => $field->options[0] ?? 'Option 1',
                RegistrationFieldType::FileUpload => 'images/lorempic.svg',
            };
        }

        $service->saveRegistrationValues($property, $regValues);

        $service->finalizeProperty($property, 'active');
    }

    /**
     * @param  Collection<string, Country>  $countries
     * @return Collection<int, User>
     */
    private function createDemoCustomers($countries): Collection
    {
        return collect(self::DEMO_CUSTOMERS)->map(function (array $def) use ($countries) {
            [$firstName, $lastName, $email, $countryName] = $def;
            $country = $countries[$countryName] ?? null;

            return User::firstOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'password' => Hash::make(self::CUSTOMER_PASSWORD),
                    'role' => UserRole::Customer,
                    'status' => UserStatus::Active,
                    'email_verified_at' => now(),
                    'country_id' => $country?->id,
                    'current_country_id' => $country?->id,
                    'dial_code' => $country ? '+'.ltrim((string) $country->phone_code, '+') : null,
                    'phone' => (string) random_int(6000000000, 9999999999),
                ]
            );
        });
    }

    /**
     * One pass per property using BOOKING_PLAN, then one review per Completed booking (the only
     * status a real guest could actually leave a review for) — mirrors the pricing/tax shape
     * PartnerPropertyCreate itself produces, not a simplified stand-in.
     *
     * @param  Collection<int, Property>  $properties
     * @param  Collection<int, User>  $customers
     * @return array{0: int, 1: int} [bookings created, reviews created]
     */
    private function createDemoBookingsAndReviews(Collection $properties, Collection $customers, int $hotelPropertyTypeId): array
    {
        if ($customers->isEmpty()) {
            return [0, 0];
        }

        $bookingCount = 0;
        $reviewCount = 0;
        $commissionService = app(CommissionService::class);

        foreach ($properties as $property) {
            $rooms = PropertyRoom::where('property_id', $property->id)->get();

            if ($rooms->isEmpty()) {
                continue;
            }

            $currency = $property->country?->currency_code ?? 'USD';
            $symbol = $property->country?->currency_symbol ?? '$';

            // Resolved once per property (country/partner/type don't change across its bookings) —
            // same call BookingService makes at real booking creation. Without this, commission_rate/
            // commission_amount stay null, which calculateCheckInPartnerCredit() silently reads as
            // 0% commission (and CommissionReport shows every demo booking as commission-free).
            $rateDetails = $commissionService->resolveRateWithDetails(
                $property->country_id,
                $property->partner_id,
                $property->property_type_id ?? 0,
            );

            // demo-partner@gmail.com (Hotel) is the one public identity — richer booking history
            // than every other partner, per explicit instruction.
            $bookingPlan = $property->property_type_id === $hotelPropertyTypeId
                ? self::HOTEL_BOOKING_PLAN
                : self::BOOKING_PLAN;

            foreach ($bookingPlan as $plan) {
                $room = $rooms->random();
                $customer = $customers->random();

                $checkIn = Carbon::today()->addDays(random_int($plan['dayOffsetMin'], $plan['dayOffsetMax']));
                $nights = random_int($plan['nightsMin'], $plan['nightsMax']);
                $checkOut = $checkIn->clone()->addDays($nights);

                $pricePerNight = (float) $room->base_price_per_night;
                // Real formula — CalculatePricingAction sums every active tax attached to this
                // property's type in this country (percentage or fixed), the exact call
                // BookingService::calculatePricing() makes. Replaces a flat 10% "Demo Tax"
                // placeholder that never reflected whatever taxes are actually configured.
                $pricing = app(CalculatePricingAction::class)->handle($room, $nights, 1, $property->country_id, $property->property_type_id);
                $baseAmount = $pricing['base_amount'];
                $taxAmount = $pricing['tax_amount'];
                $isCancelled = $plan['status'] === BookingStatus::Cancelled;
                // Expired/PendingPayment never had money collected — matches real behavior where
                // neither BookingMaintenanceService nor the payment flow ever marks these Paid.
                $neverPaid = in_array($plan['status'], [BookingStatus::Expired, BookingStatus::PendingPayment], true);
                $commissionAmount = $commissionService->calculateCommission($baseAmount, $rateDetails['rate']);

                $booking = Booking::create([
                    'booking_number' => app(BookingService::class)->generateBookingNumber(),
                    'property_id' => $property->id,
                    'property_room_id' => $room->id,
                    'user_id' => $customer->id,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'total_nights' => $nights,
                    'adults' => random_int(1, $room->roomType?->max_guests ?? 2),
                    'children' => 0,
                    'has_pets' => false,
                    'booked_rooms' => 1,
                    'guest_name' => $customer->name,
                    'guest_email' => $customer->email,
                    'guest_phone' => $customer->phone,
                    'guest_dial_code' => $customer->dial_code,
                    'price_per_night' => $pricePerNight,
                    'currency_code' => $currency,
                    'currency_symbol' => $symbol,
                    'tax_details' => $pricing['tax_details'],
                    'base_amount' => $baseAmount,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => 0,
                    'total_amount' => $pricing['total_amount'],
                    'booking_source' => BookingSource::Website,
                    'payment_status' => match (true) {
                        $isCancelled => PaymentStatus::Refunded,
                        $neverPaid => PaymentStatus::Unpaid,
                        default => PaymentStatus::Paid,
                    },
                    'payment_method' => PaymentMethod::PayOnline,
                    'status' => $plan['status'],
                    // Same call BookingService itself makes at creation time — every booking, not
                    // just cancelled ones, needs this so refund-percentage math and the "cancellation
                    // policy" section on a booking's detail page have something real to read.
                    'cancellation_policy_snapshot' => app(CancellationPolicyService::class)->buildSnapshot($property, $checkIn->toDateString()),
                    'commission_rate' => $rateDetails['rate'],
                    'commission_amount' => $commissionAmount,
                    'commission_rate_id' => $rateDetails['rate_id'],
                    'commission_source' => $rateDetails['source'],
                    'booked_country_id' => $property->country_id,
                    'booked_property_type_id' => $property->property_type_id,
                ]);

                if ($isCancelled) {
                    $booking->update([
                        'cancelled_at' => $checkIn->clone()->subDays(random_int(1, 3)),
                        'cancellation_reason' => self::CANCELLATION_REASONS[array_rand(self::CANCELLATION_REASONS)],
                        'cancelled_by' => CancellationInitiator::Customer,
                    ]);
                }

                $bookingCount++;

                if ($plan['status'] === BookingStatus::Completed) {
                    $sample = self::REVIEW_SAMPLES[array_rand(self::REVIEW_SAMPLES)];

                    // approved_by/approved_at are removal-specific (see RemovedReviewsManage,
                    // which keys its "Removed Reviews" report on approved_at) — a normal
                    // published review that was never removed must leave both null.
                    Review::create([
                        'booking_id' => $booking->id,
                        'user_id' => $booking->user_id,
                        'property_id' => $booking->property_id,
                        'property_room_id' => $booking->property_room_id,
                        'rating' => $sample['rating'],
                        'review' => $sample['text'],
                        'status' => ReviewStatus::Published->value,
                        'is_visible' => true,
                    ]);

                    $reviewCount++;
                }
            }
        }

        return [$bookingCount, $reviewCount];
    }

    /**
     * 2 low-rating, obviously-fake reviews per property (4 for Hotel — see $hotelPropertyTypeId
     * below), 80 total, for the /partner/remove-reviews demo — each needs its own completed
     * booking (a booking can only carry one review), kept entirely separate from the legitimate
     * reviews createDemoBookingsAndReviews() already gave each property. Status distribution
     * follows REMOVAL_STATUS_COUNTS exactly (not pure random), shuffled once so which property
     * gets which outcome varies from run to run.
     *
     * Mirrors ReviewRemovalRequestsManage::getApproveAction()/getRejectAction() exactly: approved
     * sets status=Removed + approved_by + approved_at (that's what makes it show up in the
     * "Removed Reviews" report); rejected/requested leave the review published and visible,
     * only removal_status differs.
     *
     * @param  Collection<int, Property>  $properties
     * @param  Collection<int, User>  $customers
     */
    private function createFakeReviewsWithRemovalRequests(Collection $properties, Collection $customers, int $hotelPropertyTypeId): int
    {
        if ($customers->isEmpty()) {
            return 0;
        }

        $admin = $this->resolveAdminUser();
        $commissionService = app(CommissionService::class);

        $statusBag = collect(self::REMOVAL_STATUS_COUNTS)
            ->flatMap(fn (int $count, string $status) => array_fill(0, $count, $status))
            ->shuffle()
            ->values();

        $created = 0;

        foreach ($properties as $property) {
            $rooms = PropertyRoom::where('property_id', $property->id)->get();

            if ($rooms->isEmpty()) {
                continue;
            }

            $currency = $property->country?->currency_code ?? 'USD';
            $symbol = $property->country?->currency_symbol ?? '$';

            $rateDetails = $commissionService->resolveRateWithDetails(
                $property->country_id,
                $property->partner_id,
                $property->property_type_id ?? 0,
            );

            // Hotel gets all 4 existing templates instead of just the first 2 — no repeats needed
            // on one property since the pool size already matches the richer count.
            $perProperty = $property->property_type_id === $hotelPropertyTypeId ? 4 : 2;

            foreach (self::FAKE_REVIEW_TEMPLATES as $index => $template) {
                if ($index >= $perProperty) {
                    break;
                }

                $room = $rooms->random();
                $customer = $customers->random();

                $checkIn = Carbon::today()->subDays(random_int(10, 60));
                $nights = random_int(1, 3);
                $checkOut = $checkIn->clone()->addDays($nights);
                $pricePerNight = (float) $room->base_price_per_night;
                $pricing = app(CalculatePricingAction::class)->handle($room, $nights, 1, $property->country_id, $property->property_type_id);
                $baseAmount = $pricing['base_amount'];
                $taxAmount = $pricing['tax_amount'];
                $commissionAmount = $commissionService->calculateCommission($baseAmount, $rateDetails['rate']);

                $booking = Booking::create([
                    'booking_number' => app(BookingService::class)->generateBookingNumber(),
                    'property_id' => $property->id,
                    'property_room_id' => $room->id,
                    'user_id' => $customer->id,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'total_nights' => $nights,
                    'adults' => 1,
                    'children' => 0,
                    'has_pets' => false,
                    'booked_rooms' => 1,
                    'guest_name' => $customer->name,
                    'guest_email' => $customer->email,
                    'guest_phone' => $customer->phone,
                    'guest_dial_code' => $customer->dial_code,
                    'price_per_night' => $pricePerNight,
                    'currency_code' => $currency,
                    'currency_symbol' => $symbol,
                    'tax_details' => $pricing['tax_details'],
                    'base_amount' => $baseAmount,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => 0,
                    'total_amount' => $pricing['total_amount'],
                    'booking_source' => BookingSource::Website,
                    'payment_status' => PaymentStatus::Paid,
                    'payment_method' => PaymentMethod::PayOnline,
                    'status' => BookingStatus::Completed,
                    'cancellation_policy_snapshot' => app(CancellationPolicyService::class)->buildSnapshot($property, $checkIn->toDateString()),
                    'commission_rate' => $rateDetails['rate'],
                    'commission_amount' => $commissionAmount,
                    'commission_rate_id' => $rateDetails['rate_id'],
                    'commission_source' => $rateDetails['source'],
                    'booked_country_id' => $property->country_id,
                    'booked_property_type_id' => $property->property_type_id,
                ]);

                $reason = self::REMOVAL_REASONS[$index] ?? self::REMOVAL_REASONS[0];
                $removalStatus = $statusBag->pop() ?? 'requested';
                $isApproved = $removalStatus === 'approved';

                Review::create([
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'property_id' => $booking->property_id,
                    'property_room_id' => $booking->property_room_id,
                    'rating' => $template['rating'],
                    'review' => $template['text'],
                    'status' => $isApproved ? ReviewStatus::Removed->value : ReviewStatus::Published->value,
                    // is_visible intentionally always true here: the real approve action
                    // (ReviewRemovalRequestsManage::getApproveAction()) only ever touches status/
                    // removal_status/approved_by/approved_at, never is_visible — matching that
                    // exactly rather than guessing a second field should also flip.
                    'is_visible' => true,
                    'removal_requested' => true,
                    'removal_status' => $removalStatus,
                    'removal_reason' => $reason['reason'],
                    'removal_description' => $reason['description'],
                    'approved_by' => $isApproved ? $admin?->id : null,
                    'approved_at' => $isApproved ? now() : null,
                ]);

                $created++;
            }
        }

        return $created;
    }

    /**
     * Real Payment/Refund rows for every booking under $properties — every "Paid" booking gets a
     * Payment, every "Refunded" (cancelled) booking gets a Payment + a Refund. The payment only
     * covers the property's own advance_percentage (pay_at_property is on for all of these), not
     * the full total — matching what CommissionService::calculateCheckInPartnerCredit() actually
     * prorates against, and what a real pay-at-property booking would have collected online.
     *
     * @param  Collection<int, Property>  $properties
     * @return array{0: int, 1: int} [payments created, refunds created]
     */
    private function createPaymentsAndRefunds(Collection $properties): array
    {
        $paymentCount = 0;
        $refundCount = 0;

        foreach ($properties as $property) {
            $gateway = self::PAYMENT_GATEWAY_BY_COUNTRY[$property->country?->name] ?? PaymentGateway::Stripe;
            $advancePct = (float) ($property->advance_percentage ?? 100);

            $bookings = Booking::where('property_id', $property->id)
                ->whereIn('payment_status', [PaymentStatus::Paid, PaymentStatus::Refunded])
                ->get();

            foreach ($bookings as $booking) {
                $onlineAmount = round((float) $booking->total_amount * ($advancePct / 100), 2);
                $paidAt = $booking->created_at ?? now();

                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'gateway_type' => $gateway,
                    'gateway_payment_id' => 'pay_'.strtoupper(Str::random(14)),
                    'gateway_order_id' => 'order_'.strtoupper(Str::random(14)),
                    'amount' => $onlineAmount,
                    'currency' => $booking->currency_code,
                    'payment_type' => $advancePct >= 100 ? PaymentType::Full : PaymentType::Partial,
                    'remaining_amount' => round((float) $booking->total_amount - $onlineAmount, 2),
                    'status' => PaymentTransactionStatus::Success,
                    'paid_at' => $paidAt,
                    'processed_at' => $paidAt,
                    'gateway_response' => ['demo' => true, 'gateway' => $gateway->value],
                ]);

                $paymentCount++;

                // The booking was created with payment_status=Paid regardless of advance_percentage
                // (see createDemoBookingsAndReviews()) — sync it down to Partial now that we know
                // this payment only ever covers the property's advance_percentage, not the full total.
                if ($advancePct < 100 && $booking->payment_status === PaymentStatus::Paid) {
                    $booking->update(['payment_status' => PaymentStatus::Partial]);
                }

                if ($booking->payment_status === PaymentStatus::Refunded) {
                    Refund::create([
                        'payment_id' => $payment->id,
                        'refund_id' => 'rfnd_'.strtoupper(Str::random(14)),
                        'amount' => $onlineAmount,
                        'refund_percentage' => 100,
                        'status' => RefundStatus::Completed,
                        'reason' => $booking->cancellation_reason ?? 'Guest cancelled the booking.',
                        'processed_at' => $paidAt,
                    ]);

                    $refundCount++;
                }
            }
        }

        return [$paymentCount, $refundCount];
    }

    /**
     * Reuses AutoAssignRoomsAction as-is — it already handles conflict-avoidance and prefers
     * consecutive room numbers, exactly matching what a real Website booking gets at confirmation
     * time. Cancelled bookings are excluded (never got that far in reality); everything else
     * (Confirmed/CheckedIn/Completed) gets a room the moment it exists, matching the action's own
     * trigger point — not just once a guest has actually checked in.
     *
     * @param  Collection<int, Property>  $properties
     */
    private function assignRoomsToBookings(Collection $properties): int
    {
        $action = app(AutoAssignRoomsAction::class);
        $assigned = 0;

        $bookings = Booking::whereIn('property_id', $properties->pluck('id'))
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->get();

        foreach ($bookings as $booking) {
            $action->handle($booking);

            if ($booking->roomAssignments()->exists()) {
                $assigned++;
            }
        }

        return $assigned;
    }

    /**
     * Populates room_inventory via the real BookingInventoryService::reserve() — without this,
     * every date these bookings cover shows as fully available on any availability calendar,
     * contradicting the bookings that actually exist. Cancelled bookings are deliberately
     * skipped: a real reserve()-then-release() nets to zero booked_rooms for past dates anyway,
     * so skipping produces the identical end state with less complexity. reserve() validates
     * capacity per date — wrapped in try/catch since 7 randomly-dated bookings per property could
     * theoretically collide on the same room type + overlapping dates beyond total_rooms; any
     * skip is logged, not silently swallowed.
     *
     * @param  Collection<int, Property>  $properties
     */
    private function reserveRoomInventory(Collection $properties): int
    {
        $inventoryService = app(BookingInventoryService::class);
        $reserved = 0;

        $bookings = Booking::whereIn('property_id', $properties->pluck('id'))
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed])
            ->with('propertyRoom')
            ->get();

        foreach ($bookings as $booking) {
            if (! $booking->propertyRoom) {
                continue;
            }

            try {
                $inventoryService->reserve(
                    $booking->propertyRoom,
                    $booking->check_in->toDateString(),
                    $booking->check_out->toDateString(),
                    $booking->booked_rooms,
                );

                $reserved++;
            } catch (\Throwable $e) {
                $this->warn("Booking #{$booking->id}: could not reserve inventory ({$e->getMessage()}).");
            }
        }

        return $reserved;
    }

    private function resolveAdminUser(): ?User
    {
        return User::whereIn('role', [UserRole::Admin, UserRole::Staff])->first();
    }

    /**
     * Reuses the app's own real crediting command rather than reimplementing the formula —
     * CommissionService::calculateCheckInPartnerCredit() is commission-aware (not a flat
     * percentage), and wallet:credit-checked-in-bookings already handles the "hasn't reached
     * check-in time yet" gating correctly. Credits Completed bookings unconditionally and
     * Confirmed/CheckedIn ones whose check-in moment has already passed.
     */
    private function creditWalletsForEligibleBookings(): int
    {
        $before = Booking::whereNotNull('wallet_credited_at')->count();
        Artisan::call('wallet:credit-checked-in-bookings');

        return Booking::whereNotNull('wallet_credited_at')->count() - $before;
    }

    /**
     * Creates one withdrawal request per property with a positive balance, then resolves most of
     * them via the real approve/reject service methods (WITHDRAWAL_STATUS_COUNTS: 21 approved —
     * genuinely debits the wallet, exactly like an admin clicking Approve — 6 left pending for
     * the admin queue, 3 rejected) rather than leaving all 30 sitting in one undifferentiated
     * pile. $properties now spans all 6 partners' properties in one call, so the owning partner
     * is resolved per-property via property->partner rather than passed in once.
     *
     * @param  Collection<int, Property>  $properties
     */
    private function requestSampleWithdrawals(Collection $properties): int
    {
        $walletService = app(PropertyWalletService::class);
        $admin = $this->resolveAdminUser();
        $requested = 0;

        $statusBag = collect(self::WITHDRAWAL_STATUS_COUNTS)
            ->flatMap(fn (int $count, string $status) => array_fill(0, $count, $status))
            ->shuffle()
            ->values();

        foreach ($properties as $property) {
            $wallet = PropertyWallet::where('property_id', $property->id)->first();

            if (! $wallet) {
                continue;
            }

            $available = $wallet->getAvailableBalance();

            if ($available <= 0) {
                continue;
            }

            $partner = $property->partner;

            if (! $partner) {
                continue;
            }

            $request = $walletService->requestWithdrawal($wallet, $partner, round($available * 0.5, 2));
            $requested++;

            $status = $statusBag->pop() ?? 'pending';

            if ($status === 'approved' && $admin) {
                $walletService->approveWithdrawal($request, $admin, 'Processed — funds transferred to registered bank account.');
            } elseif ($status === 'rejected' && $admin) {
                $walletService->rejectWithdrawal($request, $admin, 'Bank details could not be verified — please update and resubmit.');
            }
            // 'pending': leave as-is, no action.
        }

        return $requested;
    }

    /**
     * Loops all 6 PARTNER_TYPES emails and deletes whichever ones currently exist — a --fresh run
     * always resets every type-partner together, not just the one whose email happened to trigger
     * the "already exists" check.
     */
    private function deleteExistingDemoPartners(): void
    {
        foreach (self::PARTNER_TYPES as $email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $this->deleteExisting($user);
            }
        }
    }

    /**
     * Force-deletes rather than soft-deletes — this is throwaway demo data meant to be fully
     * recreatable on --fresh, and a soft-deleted row would trip the unique constraint on
     * users.email the very next time this command runs, since each demo partner always reuses
     * the same address.
     *
     * WithdrawalRequest and PropertyWalletTransaction both restrictOnDelete() against
     * property_wallets (deliberately, to protect real wallet history) — they don't cascade from
     * Property like Bookings/PropertyWallets do, so they must be cleared explicitly first or the
     * property delete below fails on the FK constraint.
     */
    private function deleteExisting(User $user): void
    {
        $partner = $user->partner;

        if ($partner) {
            $propertyIds = Property::where('partner_id', $partner->id)->pluck('id');
            $walletIds = PropertyWallet::whereIn('property_id', $propertyIds)->pluck('id');

            WithdrawalRequest::whereIn('property_wallet_id', $walletIds)->delete();
            PropertyWalletTransaction::whereIn('property_wallet_id', $walletIds)->delete();

            // reviews.property_id has no FK constraint at all, so leaving these behind wouldn't
            // error like the wallet tables above — but they'd sit as orphaned rows forever,
            // pointing at a property_id that no longer exists, on every --fresh reset.
            Review::withTrashed()->whereIn('property_id', $propertyIds)->forceDelete();

            Property::where('partner_id', $partner->id)->get()->each(fn (Property $p) => $p->forceDelete());
            RoomType::where('partner_id', $partner->id)->get()->each(fn (RoomType $rt) => $rt->forceDelete());
            $partner->forceDelete();
        }

        $user->forceDelete();
    }
}
