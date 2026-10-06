<?php

namespace Database\Seeders;

use App\Enums\AnswerType;
use App\Enums\BannerStatus;
use App\Enums\BlogCategoryStatus;
use App\Enums\BlogStatus;
use App\Enums\BookingSource;
use App\Enums\BookingStatus;
use App\Enums\CityStatus;
use App\Enums\CouponType;
use App\Enums\EventInquiryStatus;
use App\Enums\EventStatus;
use App\Enums\ManualRefundStatus;
use App\Enums\MarketingMessageAudience;
use App\Enums\MarketingMessageStatus;
use App\Enums\MarketingMessageType;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentType;
use App\Enums\PolicyType;
use App\Enums\PromoDiscountType;
use App\Enums\PropertyRuleStatus;
use App\Enums\PropertyStatus;
use App\Enums\RefundStatus;
use App\Enums\RegistrationFieldType;
use App\Enums\RoomTypeStatus;
use App\Enums\SetupTask;
use App\Enums\Status;
use App\Enums\TaxStatus;
use App\Enums\TaxType;
use App\Enums\UserQueryStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Banner;
use App\Models\Blog;
use App\Models\BlogCategory;
use App\Models\Booking;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyRule;
use App\Models\City;
use App\Models\Country;
use App\Models\CountrySetupTask;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Event;
use App\Models\EventInquiry;
use App\Models\Facility;
use App\Models\Faq;
use App\Models\FaqTopic;
use App\Models\HomepageAboutUs;
use App\Models\HomepageAmenity;
use App\Models\HowItWorksStep;
use App\Models\KeyHighlight;
use App\Models\Language;
use App\Models\LegalPolicy;
use App\Models\ManualRefundRequest;
use App\Models\MarketingMessage;
use App\Models\NearbyPlace;
use App\Models\NearbyPlaceCategory;
use App\Models\OurPromise;
use App\Models\Payment;
use App\Models\PromoCode;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyRule;
use App\Models\PropertyType;
use App\Models\RefCity;
use App\Models\RefCountry;
use App\Models\ReferralReward;
use App\Models\RefState;
use App\Models\Refund;
use App\Models\RegistrationField;
use App\Models\Review;
use App\Models\Role;
use App\Models\RoomType;
use App\Models\SocialMediaLink;
use App\Models\State;
use App\Models\Tax;
use App\Models\User;
use App\Models\UserQuery;
use App\Models\WhoWeAre;
use App\Services\PropertyService;
use App\Support\SystemMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('app.demo_mode')) {
            if ($this->command) {
                $this->command->error('DemoDataSeeder is disabled because DEMO_MODE is not enabled.');
            }

            return;
        }

        // This seeder's data model is single-mode only (properties owned directly by admin, no
        // Partner entities) — running it against a multi-mode database would create structurally
        // wrong data on top of a stray admin@gmail.com account. Refuse rather than corrupt.
        if (! SystemMode::isSingle()) {
            if ($this->command) {
                $this->command->error('DemoDataSeeder is single-mode only — refusing to run against a multi-mode database.');
            }

            return;
        }

        $users = $this->createUsers();

        $india = $this->setupCountry('India', 'IN', '91', '₹', 'INR', 'Indian Rupee', 'Maharashtra', 'Mumbai');
        $uae = $this->setupCountry('United Arab Emirates', 'AE', '971', 'د.إ', 'AED', 'UAE Dirham', 'Dubai', 'Dubai');
        $usa = $this->setupCountry('United States', 'US', '1', '$', 'USD', 'US Dollar', 'New York', 'New York City');

        $this->seedTaxes($india['country'], $uae['country'], $usa['country']);
        $this->seedCancellationPolicies($india['country'], $uae['country'], $usa['country']);
        $this->seedLegalPolicies();

        // Mark global tasks (AdminProfile + LegalPolicy) as complete
        CountrySetupTask::whereNull('country_id')
            ->update(['completed_at' => now()]);

        // Set current country for the super admin so the dashboard loads immediately.
        $admin = User::where('email', 'demomodeoff@gmail.com')->first();
        if ($admin && $india['country']) {
            $admin->update(['current_country_id' => $india['country']->id]);
        }

        // Seed more cities/states BEFORE properties so they can be assigned organically!
        $this->seedMoreCitiesAndStates($india['country'], $uae['country']);

        $properties = $this->createProperties($india, $uae, $usa);

        // Seed property rules, registration fields, and currencies BEFORE setting up properties and rooms
        $this->createPropertyRules();
        $this->createRegistrationFields($india['country']->id, $uae['country']->id, $usa['country']->id);
        $this->createCurrencies();

        $bookings = $this->createRoomsAndBookings($properties, $users);
        $this->completeProperySetup($properties);

        $this->createBanners([$india['country'], $uae['country'], $usa['country']]);
        $this->createBlogsAndFaqs();
        $this->createCompanyInfo();
        $this->createHomepageContent();
        $this->createAboutContent();
        $this->createPromotionsAndReviews($properties, $users, $bookings);

        $this->createPaymentsAndRefunds($bookings);
        $this->createEventsAndInquiries($properties);
        $this->createMarketingMessages($india['country'], $uae['country'], $usa['country']);
        $this->createReferralRewards($users, $bookings);
        $this->createNearbyPlaces($india, $uae, $usa);
        $this->createUserQueries($users);

        // Seed manual refunds, roles, and staff
        $this->createManualRefundRequests($bookings);
        $this->createRolesAndStaff($properties);

        // Set current country for demo admin — must run AFTER createRolesAndStaff() creates the user.
        $demoAdmin = User::where('email', 'admin@gmail.com')->first();
        if ($demoAdmin && $india['country']) {
            $demoAdmin->update(['current_country_id' => $india['country']->id]);
        }

        activity('system')
            ->event('seeded')
            ->withProperties([
                'summary' => 'DemoDataSeeder was executed successfully.',
            ])
            ->log('Demo data seeded');
    }

    private function seedTaxes(Country $india, Country $uae, Country $usa): void
    {
        $hotelType = PropertyType::where('name', 'Hotel')->first();

        // India: GST 18%
        $gst = Tax::create([
            'country_id' => $india->id,
            'name' => 'GST',
            'description' => 'Goods and Services Tax applicable for hotel stays in India.',
            'type' => TaxType::Percentage,
            'value' => 18,
            'status' => TaxStatus::Active,
        ]);

        // UAE: VAT 5%
        $vat = Tax::create([
            'country_id' => $uae->id,
            'name' => 'VAT',
            'description' => 'Value Added Tax applicable in the UAE.',
            'type' => TaxType::Percentage,
            'value' => 5,
            'status' => TaxStatus::Active,
        ]);

        // USA: Sales Tax 8%
        $salesTax = Tax::create([
            'country_id' => $usa->id,
            'name' => 'Sales Tax',
            'description' => 'Sales tax applicable for hotel stays in the United States.',
            'type' => TaxType::Percentage,
            'value' => 8,
            'status' => TaxStatus::Active,
        ]);

        if ($hotelType) {
            $gst->propertyTypes()->attach($hotelType->id);
            $vat->propertyTypes()->attach($hotelType->id);
            $salesTax->propertyTypes()->attach($hotelType->id);
        }

        // Mark Taxes task complete for all countries
        CountrySetupTask::where('task_key', SetupTask::Taxes->value)
            ->whereIn('country_id', [$india->id, $uae->id, $usa->id])
            ->update(['completed_at' => now()]);
    }

    private function seedCancellationPolicies(Country $india, Country $uae, Country $usa): void
    {
        foreach ([$india, $uae, $usa] as $country) {
            $policy = CancellationPolicy::create([
                'country_id' => $country->id,
                'property_type_id' => 1,
                'cancellation_cutoff_time' => 24,
                'is_active' => true,
            ]);

            CancellationPolicyRule::create([
                'cancellation_policy_id' => $policy->id,
                'days_before_checkin' => 7,
                'refund_percentage' => 100,
            ]);
            CancellationPolicyRule::create([
                'cancellation_policy_id' => $policy->id,
                'days_before_checkin' => 1,
                'refund_percentage' => 50,
            ]);
            CancellationPolicyRule::create([
                'cancellation_policy_id' => $policy->id,
                'days_before_checkin' => 0,
                'refund_percentage' => 0,
            ]);
        }

        // Mark CancellationPolicy task complete for all countries
        CountrySetupTask::where('task_key', SetupTask::CancellationPolicy->value)
            ->whereIn('country_id', [$india->id, $uae->id, $usa->id])
            ->update(['completed_at' => now()]);
    }

    private function seedLegalPolicies(): void
    {
        $language = Language::where('is_default', true)->first()
            ?? Language::first();

        $langId = $language?->id ?? 1;

        LegalPolicy::create([
            'type' => PolicyType::TermsCondition,
            'language_id' => $langId,
            'is_active' => true,
            'sections' => [
                ['title' => 'Introduction', 'content' => '<p>Welcome to eStay. By using our platform you agree to these Terms & Conditions.</p>'],
                ['title' => 'Bookings', 'content' => '<p>All bookings are subject to availability and property confirmation.</p>'],
                ['title' => 'Payments', 'content' => '<p>Payments are processed securely through our certified payment partners.</p>'],
            ],
        ]);

        LegalPolicy::create([
            'type' => PolicyType::PrivacyPolicy,
            'language_id' => $langId,
            'is_active' => true,
            'sections' => [
                ['title' => 'Data Collection', 'content' => '<p>We collect only the data necessary to provide our service.</p>'],
                ['title' => 'Data Usage', 'content' => '<p>Your data is never sold to third parties.</p>'],
                ['title' => 'Contact', 'content' => '<p>For privacy concerns contact privacy@estay.com.</p>'],
            ],
        ]);
    }

    private function createUsers(): array
    {
        User::firstOrCreate(
            ['email' => 'demomodeoff@gmail.com'],
            [
                'name' => 'Super Admin',
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::Admin,
            ]
        );

        // 40 diverse customers spanning Indian, Arab/UAE, Western, and International.
        // daysAgo is spread across the last 12 months so every dashboard time-range
        // filter (Last 7 days / Last 30 days / This Year) shows non-zero signup data.
        // platform is set so the Platform Usage doughnut chart shows a realistic split.
        $customers = [
            // ── Indian (15) ──────────────────────────────────────────────────
            ['first_name' => 'Raj',       'last_name' => 'Patel',       'email' => 'raj.patel@example.com',     'platform' => 'web',     'daysAgo' => 2],
            ['first_name' => 'Anita',     'last_name' => 'Sharma',      'email' => 'anita.sharma@example.com',  'platform' => 'android', 'daysAgo' => 5],
            ['first_name' => 'Arjun',     'last_name' => 'Singh',       'email' => 'arjun.s@example.com',       'platform' => 'web',     'daysAgo' => 9],
            ['first_name' => 'Priya',     'last_name' => 'Kumar',       'email' => 'priya.k@example.com',       'platform' => 'ios',     'daysAgo' => 14],
            ['first_name' => 'Deepak',    'last_name' => 'Mehta',       'email' => 'deepak.m@example.com',      'platform' => 'android', 'daysAgo' => 20],
            ['first_name' => 'Kavya',     'last_name' => 'Reddy',       'email' => 'kavya.r@example.com',       'platform' => 'web',     'daysAgo' => 28],
            ['first_name' => 'Rohan',     'last_name' => 'Joshi',       'email' => 'rohan.j@example.com',       'platform' => 'web',     'daysAgo' => 40],
            ['first_name' => 'Meera',     'last_name' => 'Pillai',      'email' => 'meera.p@example.com',       'platform' => 'ios',     'daysAgo' => 55],
            ['first_name' => 'Vikram',    'last_name' => 'Nair',        'email' => 'vikram.n@example.com',      'platform' => 'android', 'daysAgo' => 70],
            ['first_name' => 'Sonia',     'last_name' => 'Gupta',       'email' => 'sonia.g@example.com',       'platform' => 'web',     'daysAgo' => 88],
            ['first_name' => 'Aditya',    'last_name' => 'Bose',        'email' => 'aditya.b@example.com',      'platform' => 'web',     'daysAgo' => 105],
            ['first_name' => 'Nisha',     'last_name' => 'Verma',       'email' => 'nisha.v@example.com',       'platform' => 'android', 'daysAgo' => 122],
            ['first_name' => 'Suresh',    'last_name' => 'Iyer',        'email' => 'suresh.i@example.com',      'platform' => 'web',     'daysAgo' => 140],
            ['first_name' => 'Pooja',     'last_name' => 'Desai',       'email' => 'pooja.d@example.com',       'platform' => 'ios',     'daysAgo' => 162],
            ['first_name' => 'Amit',      'last_name' => 'Chaudhary',   'email' => 'amit.c@example.com',        'platform' => 'web',     'daysAgo' => 180],
            // ── Arab / UAE (8) ───────────────────────────────────────────────
            ['first_name' => 'Mohammed',  'last_name' => 'Ali',         'email' => 'm.ali@example.com',         'platform' => 'android', 'daysAgo' => 196],
            ['first_name' => 'Fatima',    'last_name' => 'Zahra',       'email' => 'fatima.z@example.com',      'platform' => 'web',     'daysAgo' => 210],
            ['first_name' => 'Omar',      'last_name' => 'Al-Hassan',   'email' => 'omar.h@example.com',        'platform' => 'web',     'daysAgo' => 222],
            ['first_name' => 'Layla',     'last_name' => 'Al-Rashid',   'email' => 'layla.r@example.com',       'platform' => 'ios',     'daysAgo' => 234],
            ['first_name' => 'Yousef',    'last_name' => 'Mansouri',    'email' => 'yousef.m@example.com',      'platform' => 'android', 'daysAgo' => 246],
            ['first_name' => 'Sara',      'last_name' => 'Al-Nouri',    'email' => 'sara.n@example.com',        'platform' => 'web',     'daysAgo' => 258],
            ['first_name' => 'Khalid',    'last_name' => 'Al-Farsi',    'email' => 'khalid.f@example.com',      'platform' => 'web',     'daysAgo' => 268],
            ['first_name' => 'Aisha',     'last_name' => 'Al-Zaabi',    'email' => 'aisha.z@example.com',       'platform' => 'ios',     'daysAgo' => 278],
            // ── Western (12) ─────────────────────────────────────────────────
            ['first_name' => 'John',      'last_name' => 'Doe',         'email' => 'john.doe@example.com',      'platform' => 'web',     'daysAgo' => 288],
            ['first_name' => 'Sarah',     'last_name' => 'Smith',       'email' => 'sarah.smith@example.com',   'platform' => 'android', 'daysAgo' => 296],
            ['first_name' => 'David',     'last_name' => 'Miller',      'email' => 'david.m@example.com',       'platform' => 'web',     'daysAgo' => 304],
            ['first_name' => 'Emma',      'last_name' => 'Watson',      'email' => 'emma.w@example.com',        'platform' => 'ios',     'daysAgo' => 312],
            ['first_name' => 'Michael',   'last_name' => 'Johnson',     'email' => 'michael.j@example.com',     'platform' => 'web',     'daysAgo' => 320],
            ['first_name' => 'Jessica',   'last_name' => 'Brown',       'email' => 'jessica.b@example.com',     'platform' => 'android', 'daysAgo' => 328],
            ['first_name' => 'James',     'last_name' => 'Wilson',      'email' => 'james.w@example.com',       'platform' => 'web',     'daysAgo' => 336],
            ['first_name' => 'Sophie',    'last_name' => 'Clarke',      'email' => 'sophie.c@example.com',      'platform' => 'ios',     'daysAgo' => 342],
            ['first_name' => 'Robert',    'last_name' => 'Thompson',    'email' => 'robert.t@example.com',      'platform' => 'web',     'daysAgo' => 348],
            ['first_name' => 'Charlotte', 'last_name' => 'Evans',       'email' => 'charlotte.e@example.com',   'platform' => 'android', 'daysAgo' => 353],
            ['first_name' => 'Oliver',    'last_name' => 'Harris',      'email' => 'oliver.h@example.com',      'platform' => 'web',     'daysAgo' => 357],
            ['first_name' => 'Grace',     'last_name' => 'Anderson',    'email' => 'grace.a@example.com',       'platform' => 'web',     'daysAgo' => 361],
            // ── International (5) ────────────────────────────────────────────
            ['first_name' => 'Yuki',      'last_name' => 'Tanaka',      'email' => 'yuki.t@example.com',        'platform' => 'ios',     'daysAgo' => 4],
            ['first_name' => 'Carlos',    'last_name' => 'Mendoza',     'email' => 'carlos.m@example.com',      'platform' => 'android', 'daysAgo' => 48],
            ['first_name' => 'Elena',     'last_name' => 'Petrov',      'email' => 'elena.p@example.com',       'platform' => 'web',     'daysAgo' => 135],
            ['first_name' => 'Amara',     'last_name' => 'Okonkwo',     'email' => 'amara.o@example.com',       'platform' => 'android', 'daysAgo' => 244],
            ['first_name' => 'Lin',       'last_name' => 'Wei',         'email' => 'lin.w@example.com',         'platform' => 'ios',     'daysAgo' => 364],
        ];

        $createdUsers = [];
        foreach ($customers as $c) {
            $user = User::firstOrCreate(
                ['email' => $c['email']],
                [
                    'first_name' => $c['first_name'],
                    'last_name' => $c['last_name'],
                    'name' => $c['first_name'].' '.$c['last_name'],
                    'platform' => $c['platform'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );

            if ($user->wasRecentlyCreated) {
                $this->backdateModel($user, Carbon::now()->subDays($c['daysAgo']));
            }

            $createdUsers[] = $user;
        }

        return $createdUsers;
    }

    private function setupCountry($countryName, $iso, $phone, $symbol, $code, $currencyName, $stateName, $cityName)
    {
        $refCountry = RefCountry::where('name', $countryName)->first();
        if (! $refCountry) {
            return null;
        }

        $country = Country::firstOrCreate(
            ['ref_country_id' => $refCountry->id],
            [
                'name' => $refCountry->name,
                'iso_code' => $iso,
                'phone_code' => $phone,
                'currency_symbol' => $symbol,
                'currency_code' => $code,
                'currency_name' => $currencyName,
                'is_active' => true,
                'is_default' => $countryName === 'India',
            ]
        );

        CountrySetupTask::seedForCountries([$country->id]);
        CountrySetupTask::where('country_id', $country->id)->update(['completed_at' => now()]);

        $refState = RefState::where('country_id', $refCountry->id)->where('name', $stateName)->first();
        $state = null;
        if ($refState) {
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
        }

        if ($state) {
            $refCity = RefCity::where('state_id', $refState->id)->where('name', $cityName)->first();
            if ($refCity) {
                City::firstOrCreate(
                    ['ref_city_id' => $refCity->id],
                    [
                        'country_id' => $country->id,
                        'state_id' => $state->id,
                        'name' => $refCity->name,
                        'latitude' => $refCity->latitude,
                        'longitude' => $refCity->longitude,
                        'status' => CityStatus::Active,
                    ]
                );
            }
        }

        return [
            'country' => $country,
            'state' => $state,
            'city' => City::where('name', $cityName)->first(),
        ];
    }

    private function createProperties($india, $uae, $usa): array
    {
        $created = [];

        // One "Palm Paradise - {City}" branch per city. The branch name and meta
        // are derived from the City record so the suffix always matches the city.
        $branchPlan = [
            ['ctx' => $india, 'cities' => ['Mumbai', 'Ahmedabad', 'Bhuj']],
            ['ctx' => $uae, 'cities' => ['Abu Dhabi', 'Dubai']],
            ['ctx' => $usa, 'cities' => ['New York City']],
        ];

        foreach ($branchPlan as $plan) {
            $ctx = $plan['ctx'];
            if (! $ctx || ! $ctx['country']) {
                continue;
            }

            $defs = [];
            foreach ($plan['cities'] as $cityName) {
                $city = City::where('name', $cityName)
                    ->where('country_id', $ctx['country']->id)
                    ->first();

                if (! $city) {
                    continue;
                }

                $defs[] = [
                    'country_id' => $ctx['country']->id,
                    'ref_state_id' => $city->state->ref_state_id,
                    'ref_city_id' => $city->ref_city_id,
                    'name' => 'Palm Paradise - '.$city->name,
                    'description' => '<p>Palm Paradise '.$city->name.' offers a refined stay with elegant rooms, modern amenities, and warm local hospitality in the heart of '.$city->name.'.</p>',
                    'meta_title' => 'Palm Paradise '.$city->name,
                    'street_address' => 'City Center, '.$city->name,
                    'status' => PropertyStatus::Active,
                ];
            }

            if (! empty($defs)) {
                $created = array_merge($created, $this->insertProperties($defs));
            }
        }

        return $created;
    }

    private function insertProperties(array $properties): array
    {
        $created = [];
        foreach ($properties as $prop) {
            $created[] = Property::firstOrCreate(
                ['name' => $prop['name']],
                array_merge($prop, [
                    'property_type_id' => 1,
                    'phone' => '+1234567890',
                    'email' => 'contact@'.Str::slug($prop['name']).'.com',
                    'zip_code' => '000000',
                    'check_in_time' => '14:00',
                    'check_out_time' => '11:00',
                ])
            );
        }

        return $created;
    }

    private function createRoomsAndBookings(array $properties, array $users): array
    {
        // ── 4 Room Types ──────────────────────────────────────────────────────
        $roomTypeDefs = [
            [
                'name' => 'Standard Room',
                'description' => '<p>A comfortable standard room with twin beds and all essential amenities for a pleasant stay.</p>',
                'bed_type' => 'Twin',
                'max_guests' => 2,
                'price' => 80.00,
                'total_rooms' => 15,
                'room_size' => '220 sq ft',
            ],
            [
                'name' => 'Deluxe Room',
                'description' => '<p>A spacious deluxe room with a king-size bed, city view, and premium amenities.</p>',
                'bed_type' => 'King',
                'max_guests' => 2,
                'price' => 150.00,
                'total_rooms' => 10,
                'room_size' => '300 sq ft',
            ],
            [
                'name' => 'Executive Suite',
                'description' => '<p>A lavish suite with a separate living area, jacuzzi, and personalised butler service.</p>',
                'bed_type' => 'King',
                'max_guests' => 3,
                'price' => 320.00,
                'total_rooms' => 5,
                'room_size' => '550 sq ft',
            ],
            [
                'name' => 'Family Room',
                'description' => '<p>A large family room with two queen beds, perfect for families travelling with children.</p>',
                'bed_type' => 'Double Queen',
                'max_guests' => 4,
                'price' => 200.00,
                'total_rooms' => 8,
                'room_size' => '420 sq ft',
            ],
        ];

        $roomTypes = [];
        foreach ($roomTypeDefs as $def) {
            $roomTypes[] = RoomType::firstOrCreate(
                ['name' => $def['name']],
                [
                    'description' => $def['description'],
                    'bed_type' => $def['bed_type'],
                    'max_guests' => $def['max_guests'],
                    'status' => RoomTypeStatus::Active,
                ]
            );
        }

        // Attach each room type to every property
        $propertyRooms = []; // indexed by property->id => [room, ...]
        foreach ($properties as $property) {
            $propertyRooms[$property->id] = [];
            foreach ($roomTypeDefs as $idx => $def) {
                $rt = $roomTypes[$idx];
                $propertyRooms[$property->id][] = PropertyRoom::firstOrCreate(
                    ['property_id' => $property->id, 'room_type_id' => $rt->id],
                    [
                        'total_rooms' => $def['total_rooms'],
                        'room_size' => $def['room_size'],
                        'base_price_per_night' => $def['price'],
                    ]
                );
            }
        }

        // ── Booking Data ───────────────────────────────────────────────────────
        $statuses = [
            BookingStatus::Confirmed,
            BookingStatus::Confirmed,
            BookingStatus::Confirmed,
            BookingStatus::Confirmed,
            BookingStatus::CheckedIn,
            BookingStatus::CheckedIn,
            BookingStatus::Completed,
            BookingStatus::Completed,
            BookingStatus::Completed,
            BookingStatus::Cancelled,
            BookingStatus::Pending,
        ];

        $sources = [
            BookingSource::Website,
            BookingSource::Website,
            BookingSource::Website,
            BookingSource::Application,
            BookingSource::Application,
            BookingSource::Admin,
        ];

        $paymentMethods = [
            PaymentMethod::PayOnline,
            PaymentMethod::PayOnline,
            PaymentMethod::PayOnline,
            PaymentMethod::PayAtProperty,
            PaymentMethod::PayAtProperty,
        ];

        $guestDialCodes = ['+91', '+971', '+1', '+44', '+61'];
        $guestPhones = ['9876543210', '0501234567', '2125551234', '7911123456', '412555678'];

        $allBookings = [];

        // Generate at least 8 bookings per property (6 properties × 8 = 48 min)
        foreach ($properties as $property) {
            $rooms = $propertyRooms[$property->id];
            $currency = $this->countryProfile($property->country_id);

            for ($i = 0; $i < 8; $i++) {
                $room = $rooms[array_rand($rooms)];
                $user = $users[array_rand($users)];
                $status = $statuses[array_rand($statuses)];

                // Spread bookings across past, present, future
                $dayOffset = match (true) {
                    $status === BookingStatus::Completed => rand(-45, -3),
                    $status === BookingStatus::CheckedIn => rand(-2, 0),
                    $status === BookingStatus::Cancelled => rand(-30, -1),
                    $status === BookingStatus::Pending => rand(1, 10),
                    default => rand(-5, 30),
                };

                $checkIn = Carbon::today()->addDays($dayOffset);
                $nights = rand(1, 7);
                $checkOut = (clone $checkIn)->addDays($nights);

                $pricePerNight = $room->base_price_per_night;
                $baseAmount = $pricePerNight * $nights;
                $taxAmount = round($baseAmount * $currency['taxRate'], 2);

                $dialIdx = array_rand($guestDialCodes);

                $booking = Booking::create([
                    'booking_number' => 'BKG-'.strtoupper(Str::random(8)),
                    'property_id' => $property->id,
                    'property_room_id' => $room->id,
                    'user_id' => $user->id,
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'total_nights' => $nights,
                    'adults' => rand(1, $room->roomType->max_guests ?? 2),
                    'children' => rand(0, 1),
                    'has_pets' => false,
                    'booked_rooms' => 1,
                    'guest_name' => $user->first_name.' '.$user->last_name,
                    'guest_email' => $user->email,
                    'guest_phone' => $guestPhones[$dialIdx],
                    'guest_dial_code' => $guestDialCodes[$dialIdx],
                    'price_per_night' => $pricePerNight,
                    'currency_code' => $currency['code'],
                    'currency_symbol' => $currency['symbol'],
                    'tax_details' => [['name' => $currency['taxName'], 'amount' => $taxAmount]],
                    'base_amount' => $baseAmount,
                    'tax_amount' => $taxAmount,
                    'discount_amount' => 0,
                    'total_amount' => $baseAmount + $taxAmount,
                    'booking_source' => $sources[array_rand($sources)],
                    'payment_status' => $status === BookingStatus::Cancelled ? PaymentStatus::Refunded : PaymentStatus::Paid,
                    'payment_method' => $paymentMethods[array_rand($paymentMethods)],
                    'status' => $status,
                ]);
                $this->backdateModel($booking, $this->bookingCreatedAt($checkIn));
                $allBookings[] = $booking;
            }
        }

        // Extra bookings to reach 60+ total and simulate a busier history
        $extraBookingData = [
            ['nights' => 3, 'dayOffset' => -60, 'status' => BookingStatus::Completed],
            ['nights' => 2, 'dayOffset' => -50, 'status' => BookingStatus::Completed],
            ['nights' => 5, 'dayOffset' => -40, 'status' => BookingStatus::Completed],
            ['nights' => 1, 'dayOffset' => -35, 'status' => BookingStatus::Cancelled],
            ['nights' => 4, 'dayOffset' => -25, 'status' => BookingStatus::Completed],
            ['nights' => 7, 'dayOffset' => 10,  'status' => BookingStatus::Confirmed],
            ['nights' => 2, 'dayOffset' => 15,  'status' => BookingStatus::Confirmed],
            ['nights' => 3, 'dayOffset' => 20,  'status' => BookingStatus::Confirmed],
            ['nights' => 1, 'dayOffset' => -70, 'status' => BookingStatus::Completed],
            ['nights' => 6, 'dayOffset' => -45, 'status' => BookingStatus::Completed],
            ['nights' => 3, 'dayOffset' => -30, 'status' => BookingStatus::Completed],
            ['nights' => 2, 'dayOffset' => -15, 'status' => BookingStatus::Completed],
            ['nights' => 4, 'dayOffset' => -1,  'status' => BookingStatus::CheckedIn],
            ['nights' => 5, 'dayOffset' => 5,   'status' => BookingStatus::Confirmed],
            ['nights' => 2, 'dayOffset' => 12,  'status' => BookingStatus::Confirmed],
            ['nights' => 3, 'dayOffset' => 25,  'status' => BookingStatus::Confirmed],
        ];

        foreach ($extraBookingData as $extra) {
            $property = $properties[array_rand($properties)];
            $rooms = $propertyRooms[$property->id];
            $room = $rooms[array_rand($rooms)];
            $user = $users[array_rand($users)];
            $currency = $this->countryProfile($property->country_id);

            $checkIn = Carbon::today()->addDays($extra['dayOffset']);
            $checkOut = (clone $checkIn)->addDays($extra['nights']);
            $base = $room->base_price_per_night * $extra['nights'];
            $tax = round($base * $currency['taxRate'], 2);

            $booking = Booking::create([
                'booking_number' => 'BKG-'.strtoupper(Str::random(8)),
                'property_id' => $property->id,
                'property_room_id' => $room->id,
                'user_id' => $user->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_nights' => $extra['nights'],
                'adults' => 2,
                'children' => 0,
                'has_pets' => false,
                'booked_rooms' => 1,
                'guest_name' => $user->first_name.' '.$user->last_name,
                'guest_email' => $user->email,
                'guest_phone' => '9876543210',
                'guest_dial_code' => '+91',
                'price_per_night' => $room->base_price_per_night,
                'currency_code' => $currency['code'],
                'currency_symbol' => $currency['symbol'],
                'tax_details' => [['name' => $currency['taxName'], 'amount' => $tax]],
                'base_amount' => $base,
                'tax_amount' => $tax,
                'discount_amount' => 0,
                'total_amount' => $base + $tax,
                'booking_source' => BookingSource::Website,
                'payment_status' => $extra['status'] === BookingStatus::Cancelled ? PaymentStatus::Refunded : PaymentStatus::Paid,
                'payment_method' => PaymentMethod::PayOnline,
                'status' => $extra['status'],
            ]);
            $this->backdateModel($booking, $this->bookingCreatedAt($checkIn));
            $allBookings[] = $booking;
        }

        // Seed bookings with check-in and check-out on the same day, spread across the last 7 days
        for ($i = 0; $i < 15; $i++) {
            $property = $properties[$i % count($properties)];
            $rooms = $propertyRooms[$property->id];
            $room = $rooms[array_rand($rooms)];
            $user = $users[array_rand($users)];
            $currency = $this->countryProfile($property->country_id);

            // Spread across last 7 days (dayOffset from -7 to 0)
            $dayOffset = ($i % 8) * -1; // 0, -1, -2, -3, -4, -5, -6, -7
            $checkIn = Carbon::today()->addDays($dayOffset);
            $checkOut = Carbon::today()->addDays($dayOffset);
            $nights = 0;
            $pricePerNight = $room->base_price_per_night;
            $baseAmount = $pricePerNight; // Charge 1 night price for same-day booking
            $taxAmount = round($baseAmount * $currency['taxRate'], 2);

            // If it's a past date (offset < 0), mark it Completed.
            // If it's today (offset === 0), mark it CheckedIn or Confirmed.
            $status = $dayOffset < 0
                ? BookingStatus::Completed
                : ($i % 2 === 0 ? BookingStatus::Confirmed : BookingStatus::CheckedIn);

            $booking = Booking::create([
                'booking_number' => 'BKG-'.strtoupper(Str::random(8)),
                'property_id' => $property->id,
                'property_room_id' => $room->id,
                'user_id' => $user->id,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'total_nights' => $nights,
                'adults' => 2,
                'children' => 0,
                'has_pets' => false,
                'booked_rooms' => 1,
                'guest_name' => $user->first_name.' '.$user->last_name,
                'guest_email' => $user->email,
                'guest_phone' => '9876543210',
                'guest_dial_code' => '+91',
                'price_per_night' => $pricePerNight,
                'currency_code' => $currency['code'],
                'currency_symbol' => $currency['symbol'],
                'tax_details' => [['name' => $currency['taxName'], 'amount' => $taxAmount]],
                'base_amount' => $baseAmount,
                'tax_amount' => $taxAmount,
                'discount_amount' => 0,
                'total_amount' => $baseAmount + $taxAmount,
                'booking_source' => BookingSource::Website,
                'payment_status' => PaymentStatus::Paid,
                'payment_method' => PaymentMethod::PayOnline,
                'status' => $status,
            ]);
            $this->backdateModel($booking, $this->bookingCreatedAt($checkIn));
            $allBookings[] = $booking;
        }

        return $allBookings;
    }

    private function completeProperySetup(array $properties): void
    {
        $service = app(PropertyService::class);

        // Step 2: Facilities — sync 6 active facilities to every property
        $facilityIds = Facility::where('status', 'active')
            ->limit(6)
            ->pluck('id')
            ->toArray();

        // Step 4: Property Rules — find all active rules and their questions
        $activeRules = PropertyRule::where('status', PropertyRuleStatus::Active)
            ->with(['questions'])
            ->get();

        foreach ($properties as $property) {
            // ── Step 2: Sync facilities ────────────────────────────────────
            if (! empty($facilityIds)) {
                $service->syncFacilities($property, $facilityIds);
            }

            // ── Step 3: Already done (rooms seeded), just mark it complete ─
            $service->completeRoomsStep($property);

            // ── Step 4: Rule answers + check-in/out times ─────────────────
            $answers = [];
            foreach ($activeRules as $rule) {
                foreach ($rule->questions as $question) {
                    $answers[$question->id] = match ($question->answer_type) {
                        AnswerType::YesNo => true,
                        AnswerType::SingleSelect => $question->options[0] ?? 'yes',
                        AnswerType::MultipleSelect => [$question->options[0] ?? 'yes'],
                    };
                }
            }
            $service->saveRuleAnswers($property, [
                'check_in_time' => '14:00',
                'check_out_time' => '11:00',
                'pets_allowed' => false,
                'custom_rules' => 'No smoking inside the premises. Noise curfew after 10 PM.',
                'answers' => $answers,
            ]);

            // ── Step 5: Cancellation policy (country-level policy exists) ──
            $service->completeCancellationPolicyStep($property);

            // ── Step 6: Payment configuration ─────────────────────────────
            $service->savePaymentConfig($property, [
                'pay_at_property' => false,
                'advance_percentage' => null,
            ]);

            // ── Step 7: Primary images (5 placeholder images) ─────────────
            $primaryImages = array_fill(0, 5, 'images/lorempic.svg');
            $service->saveImages($property, $primaryImages, [], null);

            // ── Step 8: Registration field values + finalize ───────────────
            $regFields = RegistrationField::where('country_id', $property->country_id)
                ->where('property_type_id', $property->property_type_id)
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

            // Finalize as active
            $service->finalizeProperty($property, 'active');
        }
    }

    private function createBanners(array $countries): void
    {
        $india = $countries[0];
        $uae = $countries[1];
        $usa = $countries[2];

        // India Banners
        Banner::create([
            'country_id' => $india->id,
            'is_global' => false,
            'platform' => 'Web',
            'title' => 'Explore the Colors of India',
            'image' => '/images/lorempic.svg',
            'target_url' => '/search?country=india',
            'status' => BannerStatus::Active,
            'sort_order' => 1,
        ]);

        // UAE Banners
        Banner::create([
            'country_id' => $uae->id,
            'is_global' => false,
            'platform' => 'Web',
            'title' => 'Luxury Stays in the UAE',
            'image' => '/images/lorempic.svg',
            'target_url' => '/search?country=uae',
            'status' => BannerStatus::Active,
            'sort_order' => 1,
        ]);

        // USA Banners
        Banner::create([
            'country_id' => $usa->id,
            'is_global' => false,
            'platform' => 'Web',
            'title' => 'Discover Iconic Stays Across the USA',
            'image' => '/images/lorempic.svg',
            'target_url' => '/search?country=usa',
            'status' => BannerStatus::Active,
            'sort_order' => 1,
        ]);
    }

    private function createBlogsAndFaqs(): void
    {
        $admin = User::where('email', 'demomodeoff@gmail.com')->first();
        $adminId = $admin?->id ?? 1;

        $cat1 = BlogCategory::create([
            'name' => 'Travel Guides',
            'slug' => 'travel-guides',
            'status' => BlogCategoryStatus::Published,
        ]);

        $cat2 = BlogCategory::create([
            'name' => 'Local Cuisine',
            'slug' => 'local-cuisine',
            'status' => BlogCategoryStatus::Published,
        ]);

        $cat3 = BlogCategory::create([
            'name' => 'Safety & Travel Tips',
            'slug' => 'safety-travel-tips',
            'status' => BlogCategoryStatus::Published,
        ]);

        Blog::create([
            'blog_category_id' => $cat1->id,
            'created_by' => $adminId,
            'title' => 'Top 10 Beaches in Goa for 2026',
            'slug' => 'top-10-beaches-in-goa',
            'short_description' => 'Discover the best beaches for relaxation and partying in Goa.',
            'cover_image' => '/images/lorempic.svg',
            'content' => '<p>Goa is famous for its beaches. Here is our top 10 list including Calangute, Baga, and Palolem beach...</p>',
            'status' => BlogStatus::Published,
            'published_at' => now(),
            'read_time_minutes' => 5,
        ]);

        Blog::create([
            'blog_category_id' => $cat1->id,
            'created_by' => $adminId,
            'title' => 'Best Places to Shop in Dubai Marina',
            'slug' => 'best-places-to-shop-in-dubai-marina',
            'short_description' => 'A comprehensive guide to shopping malls and boutiques in Dubai Marina.',
            'cover_image' => '/images/lorempic.svg',
            'content' => '<p>Dubai Marina offers spectacular shopping experiences. Here is where you should go...</p>',
            'status' => BlogStatus::Published,
            'published_at' => now()->subDays(2),
            'read_time_minutes' => 6,
        ]);

        Blog::create([
            'blog_category_id' => $cat2->id,
            'created_by' => $adminId,
            'title' => 'Authentic Maharashtrian Dishes You Must Try in Mumbai',
            'slug' => 'authentic-maharashtrian-dishes-in-mumbai',
            'short_description' => 'Explore the rich flavors of Mumbai local cuisine from Vada Pav to Misal Pav.',
            'cover_image' => '/images/lorempic.svg',
            'content' => '<p>Mumbai food culture is incredible. Do not miss these top dishes when you stay here...</p>',
            'status' => BlogStatus::Published,
            'published_at' => now()->subDays(4),
            'read_time_minutes' => 4,
        ]);

        Blog::create([
            'blog_category_id' => $cat3->id,
            'created_by' => $adminId,
            'title' => 'Packing Essentials for a Beach Resort Vacation',
            'slug' => 'packing-essentials-beach-resort-vacation',
            'short_description' => 'What to pack for your ultimate beach resort holiday to ensure you do not miss anything.',
            'cover_image' => '/images/lorempic.svg',
            'content' => '<p>Getting ready for a beach getaway? Use our handy checklist to pack smart...</p>',
            'status' => BlogStatus::Published,
            'published_at' => now()->subDays(7),
            'read_time_minutes' => 5,
        ]);

        Blog::create([
            'blog_category_id' => $cat3->id,
            'created_by' => $adminId,
            'title' => 'How to Get the Best Deals on Luxury Hotel Bookings',
            'slug' => 'best-deals-on-luxury-hotel-bookings',
            'short_description' => 'Pro tips and strategies for finding cheap rates and deals on luxury hotels.',
            'cover_image' => '/images/lorempic.svg',
            'content' => '<p>Want to stay in 5-star hotels without paying full price? Here are the best tips...</p>',
            'status' => BlogStatus::Published,
            'published_at' => now()->subDays(10),
            'read_time_minutes' => 7,
        ]);

        $topic1 = FaqTopic::create([
            'title' => 'Booking & Cancellations',
            'slug' => 'booking-cancellations',
            'description' => 'Everything you need to know about booking and cancelling your stays.',
            'sort_order' => 1,
        ]);

        $topic2 = FaqTopic::create([
            'title' => 'Payments & Invoices',
            'slug' => 'payments-invoices',
            'description' => 'Details about billing, invoices, payment methods, and refunds.',
            'sort_order' => 2,
        ]);

        $topic3 = FaqTopic::create([
            'title' => 'Check-in & Stay Policies',
            'slug' => 'checkin-stay-policies',
            'description' => 'Important details regarding check-in times, guest IDs, and property rules.',
            'sort_order' => 3,
        ]);

        Faq::create([
            'faq_topic_id' => $topic1->id,
            'question' => 'What is your cancellation policy?',
            'answer' => '<p>You can cancel for free up to 24 hours before your check-in time depending on the property policy.</p>',
            'sort_order' => 1,
        ]);

        Faq::create([
            'faq_topic_id' => $topic1->id,
            'question' => 'Can I book a hotel on behalf of someone else?',
            'answer' => '<p>Yes, you can enter the guest name and contact details during the booking process to book for them.</p>',
            'sort_order' => 2,
        ]);

        Faq::create([
            'faq_topic_id' => $topic2->id,
            'question' => 'What payment methods do you accept?',
            'answer' => '<p>We accept all major credit/debit cards, Net Banking, UPI (in India), and Pay At Property options.</p>',
            'sort_order' => 1,
        ]);

        Faq::create([
            'faq_topic_id' => $topic2->id,
            'question' => 'How can I download my booking invoice?',
            'answer' => '<p>Once your payment is successful, you will receive a booking confirmation email with a link to download the PDF invoice.</p>',
            'sort_order' => 2,
        ]);

        Faq::create([
            'faq_topic_id' => $topic3->id,
            'question' => 'What are the standard check-in and check-out times?',
            'answer' => '<p>Standard check-in is at 2:00 PM and check-out is at 11:00 AM. Early check-in is subject to availability.</p>',
            'sort_order' => 1,
        ]);

        Faq::create([
            'faq_topic_id' => $topic3->id,
            'question' => 'Do I need to present a physical ID card during check-in?',
            'answer' => '<p>Yes, all adult guests must present a valid government-issued photo ID (Passport, Driving License, Aadhar card, etc.) at check-in.</p>',
            'sort_order' => 2,
        ]);

        Faq::create([
            'faq_topic_id' => $topic3->id,
            'question' => 'Are pets allowed in all properties?',
            'answer' => '<p>Pet policies vary by property. Please check the property rules section before booking to see if pets are allowed.</p>',
            'sort_order' => 3,
        ]);
    }

    private function createCompanyInfo(): void
    {
        WhoWeAre::create([
            'badge_text' => 'About Us',
            'title' => 'About eStay',
            'short_description' => 'eStay is your premier partner for finding luxury and budget accommodations worldwide.',
            'content' => '<p>We believe in seamless travel experiences and making booking hotels as easy as possible. A world where travel is accessible to everyone.</p>',
            'image' => '/images/lorempic.svg',
        ]);

        HomepageAboutUs::create([
            'title' => 'Why Choose Us',
            'description' => '<p>With over 1,000 properties worldwide, eStay offers the best prices and 24/7 customer support.</p>',
            'image' => '/images/lorempic.svg',
            'button_text' => 'Learn More',
            'contact_no' => '+1234567890',
            'is_active' => true,
        ]);

        SocialMediaLink::create([
            'link' => 'https://facebook.com/estay',
            'image' => '/images/lorempic.svg',
        ]);
        SocialMediaLink::create([
            'link' => 'https://instagram.com/estay',
            'image' => '/images/lorempic.svg',
        ]);

        // Activate Hotel property type
        PropertyType::where('name', 'Hotel')
            ->update(['is_active' => true, 'is_default' => true]);
    }

    private function createHomepageContent(): void
    {
        // How It Works Steps (help-support page)
        $steps = [
            ['title' => 'Search Your Stay', 'description' => 'Browse thousands of verified hotels, villas, and homestays across your preferred destination.', 'sort_order' => 1],
            ['title' => 'Book Instantly', 'description' => 'Select your dates, choose your room, and confirm your booking in under 2 minutes.', 'sort_order' => 2],
            ['title' => 'Enjoy Your Trip', 'description' => 'Arrive at your property, check in seamlessly, and enjoy a memorable stay.', 'sort_order' => 3],
        ];
        foreach ($steps as $step) {
            HowItWorksStep::create($step);
        }

        // Homepage Amenities — pick first 4 active facilities
        $facilities = Facility::where('status', 'active')->limit(4)->get();
        foreach ($facilities as $index => $facility) {
            HomepageAmenity::create([
                'facility_id' => $facility->id,
                'description' => 'Enjoy top-class '.strtolower($facility->name).' facilities at every eStay property.',
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }
    }

    private function createAboutContent(): void
    {
        // Key Highlights (manage-about page)
        $highlights = [
            ['title' => '1,000+ Properties', 'description' => 'Across India, UAE & USA', 'sort_order' => 1],
            ['title' => '50,000+ Guests', 'description' => 'Happy travelers served', 'sort_order' => 2],
            ['title' => '4.8★ Rating', 'description' => 'Average guest satisfaction', 'sort_order' => 3],
            ['title' => '24/7 Support', 'description' => 'Round-the-clock assistance', 'sort_order' => 4],
        ];
        foreach ($highlights as $h) {
            KeyHighlight::create($h);
        }

        // Our Promise (manage-about page)
        OurPromise::create([
            'badge_text' => 'Our Promise',
            'title' => 'We Promise the Best Experience',
            'content' => '<p>At eStay, we are committed to delivering exceptional hospitality experiences. Every property on our platform is verified, every booking is secured, and every guest matters.</p>',
            'features' => [
                'Verified & inspected properties',
                'Transparent pricing with no hidden fees',
                'Instant booking confirmation',
                'Dedicated 24/7 customer support',
            ],
        ]);
    }

    private function createPromotionsAndReviews(array $properties, array $users, array $bookings): void
    {
        // Reviews - create realistic reviews for completed bookings
        $completedBookings = array_filter($bookings, fn ($b) => $b->status === BookingStatus::Completed);

        $reviewTexts = [
            'Absolutely loved my stay here! The service was fantastic and the location is perfect. Will definitely visit again.',
            'The rooms were clean, very comfortable, and the staff was amazing. They went over and beyond to help make our stay enjoyable.',
            'Wonderful experience! The staff was friendly, the food was delicious, and the view from our room was stunning.',
            'Great value for money. The facilities were top-notch and the check-in process was very smooth.',
            'Excellent hospitality. The room was spacious and well-maintained. Highly recommend for families.',
            'Had a great time. The pool was amazing, and the staff was extremely helpful. 5 stars!',
            'Beautiful property with a very peaceful atmosphere. Perfect for a weekend getaway.',
            'Outstanding service. The breakfast spread was huge and tasty. Will recommend to all my friends.',
        ];

        $reviewIndex = 0;
        foreach ($completedBookings as $booking) {
            if ($reviewIndex >= 18) {
                break;
            }

            Review::create([
                'booking_id' => $booking->id,
                'user_id' => $booking->user_id,
                'property_id' => $booking->property_id,
                'property_room_id' => $booking->property_room_id,
                'rating' => rand(4, 5) + (rand(0, 9) / 10),
                'review' => $reviewTexts[$reviewIndex % count($reviewTexts)],
                'status' => 'published',
                'is_visible' => true,
                'is_featured' => $reviewIndex % 3 === 0,
                'approved_by' => 1,
                'approved_at' => now(),
            ]);
            $reviewIndex++;
        }

        // Promo Codes
        PromoCode::create([
            'code' => 'SUMMER20',
            'title' => 'Summer Sale 20% Off',
            'discount_type' => PromoDiscountType::Percentage,
            'discount_value' => 20,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonths(3),
            'usage_limit' => 100,
            'is_active' => true,
            'country_id' => $properties[0]->country_id ?? 1,
        ]);

        PromoCode::create([
            'code' => 'WINTER15',
            'title' => 'Winter Special 15% Off',
            'discount_type' => PromoDiscountType::Percentage,
            'discount_value' => 15,
            'start_date' => now()->subDays(10),
            'end_date' => now()->addMonths(6),
            'usage_limit' => 200,
            'is_active' => true,
            'country_id' => $properties[0]->country_id ?? 1,
        ]);

        PromoCode::create([
            'code' => 'FESTIVE25',
            'title' => 'Festive Celebration 25% Off',
            'discount_type' => PromoDiscountType::Percentage,
            'discount_value' => 25,
            'start_date' => now()->subDay(),
            'end_date' => now()->addMonths(2),
            'usage_limit' => 150,
            'is_active' => true,
            'country_id' => $properties[1]->country_id ?? 2,
        ]);

        // Coupons
        Coupon::create([
            'code' => 'WELCOME50',
            'type' => CouponType::Fixed,
            'value' => 50,
            'expires_at' => now()->addMonths(3),
            'user_id' => $users[0]->id ?? 1,
        ]);

        Coupon::create([
            'code' => 'LOYAL30',
            'type' => CouponType::Fixed,
            'value' => 30,
            'expires_at' => now()->addMonths(6),
            'user_id' => $users[1]->id ?? 2,
        ]);

        Coupon::create([
            'code' => 'SPECIAL20',
            'type' => CouponType::Fixed,
            'value' => 20,
            'expires_at' => now()->addMonths(4),
            'user_id' => $users[2]->id ?? 3,
        ]);
    }

    private function createPaymentsAndRefunds(array $bookings): void
    {
        foreach ($bookings as $booking) {
            if ($booking->payment_status === PaymentStatus::Paid || $booking->payment_status === PaymentStatus::Refunded) {
                $gateway = $booking->currency_code === 'INR' ? PaymentGateway::Razorpay : PaymentGateway::Stripe;

                $payment = Payment::create([
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'gateway_type' => $gateway,
                    'gateway_payment_id' => 'pay_'.Str::random(14),
                    'gateway_order_id' => 'ord_'.Str::random(14),
                    'amount' => $booking->total_amount,
                    'currency' => $booking->currency_code,
                    'converted_amount' => $booking->total_amount,
                    'payment_type' => PaymentType::Full,
                    'remaining_amount' => 0.00,
                    'status' => $booking->payment_status === PaymentStatus::Refunded
                        ? PaymentTransactionStatus::Refunded
                        : PaymentTransactionStatus::Success,
                    'paid_at' => $booking->created_at,
                    'processed_at' => $booking->created_at,
                    'gateway_response' => ['status' => 'captured'],
                ]);

                if ($booking->payment_status === PaymentStatus::Refunded) {
                    Refund::create([
                        'payment_id' => $payment->id,
                        'refund_id' => 're_'.Str::random(14),
                        'amount' => $booking->total_amount,
                        'refund_percentage' => 100.00,
                        'status' => RefundStatus::Completed,
                        'reason' => 'Customer requested cancellation.',
                        'processed_at' => $booking->created_at->addHours(12),
                    ]);
                }
            } elseif ($booking->payment_status === PaymentStatus::Partial) {
                $gateway = $booking->currency_code === 'INR' ? PaymentGateway::Razorpay : PaymentGateway::Stripe;
                $halfAmount = round($booking->total_amount / 2, 2);

                Payment::create([
                    'booking_id' => $booking->id,
                    'user_id' => $booking->user_id,
                    'gateway_type' => $gateway,
                    'gateway_payment_id' => 'pay_'.Str::random(14),
                    'gateway_order_id' => 'ord_'.Str::random(14),
                    'amount' => $halfAmount,
                    'currency' => $booking->currency_code,
                    'converted_amount' => $halfAmount,
                    'payment_type' => PaymentType::Partial,
                    'remaining_amount' => round($booking->total_amount - $halfAmount, 2),
                    'status' => PaymentTransactionStatus::Success,
                    'paid_at' => $booking->created_at,
                    'processed_at' => $booking->created_at,
                    'gateway_response' => ['status' => 'captured'],
                ]);
            }
        }
    }

    private function createEventsAndInquiries(array $properties): void
    {
        $eventData = [
            [
                'title' => 'Luxury Beachfront Wedding',
                'description' => 'Host your dream wedding on the sandy shores with full catering, flower decorations, and seating for up to 200 guests.',
                'features' => ['Beach access', 'Full catering', 'Decorations included', 'Sound system'],
            ],
            [
                'title' => 'Corporate Business Summit',
                'description' => 'Professional meeting spaces equipped with high-speed internet, smart projectors, soundproofing, and gourmet coffee stations.',
                'features' => ['High-speed Wi-Fi', 'Smart Projectors', 'Coffee & Tea station', 'Executive seating'],
            ],
            [
                'title' => 'Sunset Yoga & Wellness Session',
                'description' => 'Rejuvenate your mind and body with guided yoga sessions led by certified instructors overlooking the scenic skyline.',
                'features' => ['Yoga mats provided', 'Organic juices', 'Certified instructor', 'Sunset view'],
            ],
        ];

        $inquiryNames = ['Karan Johar', 'Sarah Connor', 'Amitabh Bachchan', 'Emma Watson', 'Zayn Malik'];
        $inquiryEmails = ['karan@example.com', 'sarah.c@example.com', 'amitabh@example.com', 'emma@example.com', 'zayn@example.com'];

        foreach ($properties as $property) {
            foreach ($eventData as $idx => $data) {
                $event = Event::create([
                    'property_id' => $property->id,
                    'title' => $data['title'].' at '.$property->name,
                    'description' => $data['description'],
                    'image_path' => '/images/lorempic.svg',
                    'features' => $data['features'],
                    'status' => EventStatus::Active,
                ]);

                for ($j = 0; $j < 2; $j++) {
                    $uIdx = ($idx * 2 + $j) % count($inquiryNames);
                    EventInquiry::create([
                        'inquiry_number' => 'INQ-'.strtoupper(Str::random(8)),
                        'property_id' => $property->id,
                        'event_id' => $event->id,
                        'name' => $inquiryNames[$uIdx],
                        'email' => $inquiryEmails[$uIdx],
                        'dial_code' => $this->countryProfile($property->country_id)['dialCode'],
                        'phone' => '98765'.rand(10000, 99999),
                        'message' => 'I would like to inquire about hosting my event next month. Please share pricing and availability.',
                        'status' => $j === 0 ? EventInquiryStatus::Pending : EventInquiryStatus::Contacted,
                    ]);
                }
            }
        }
    }

    private function createMarketingMessages(Country $india, Country $uae, Country $usa): void
    {
        $admin = User::where('email', 'demomodeoff@gmail.com')->first();
        $adminId = $admin?->id ?? 1;

        $campaigns = [
            [
                'country' => $india,
                'title' => 'Monsoon Magic: 20% Off Stays in India',
                'body' => 'Enjoy the rainy season with our exclusive monsoon getaways. Book now and get an extra 20% discount on luxury suites.',
                'type' => MarketingMessageType::Email,
                'sent_to' => 1500,
                'open_count' => 645,
                'click_count' => 210,
                'status' => MarketingMessageStatus::Sent,
                'sent_at' => now()->subDays(5),
            ],
            [
                'country' => $uae,
                'title' => 'Summer Luxury Suite Offers in Dubai',
                'body' => 'Beat the heat with premium cooling pools and indoor activities. Exclusive packages for UAE residents.',
                'type' => MarketingMessageType::Push,
                'sent_to' => 800,
                'open_count' => 450,
                'click_count' => 180,
                'status' => MarketingMessageStatus::Sent,
                'sent_at' => now()->subDays(2),
            ],
            [
                'country' => $india,
                'title' => 'Upcoming Festive Season Discounts',
                'body' => 'Pre-book your holiday stays for the upcoming festive season and secure the best prices before they rise.',
                'type' => MarketingMessageType::Both,
                'sent_to' => 2500,
                'open_count' => 0,
                'click_count' => 0,
                'status' => MarketingMessageStatus::Scheduled,
                'scheduled_at' => now()->addDays(3),
            ],
            [
                'country' => $usa,
                'title' => 'Weekend Getaways in New York City',
                'body' => 'Escape to the city that never sleeps. Book your Manhattan stay now and enjoy exclusive weekend rates.',
                'type' => MarketingMessageType::Email,
                'sent_to' => 1200,
                'open_count' => 540,
                'click_count' => 165,
                'status' => MarketingMessageStatus::Sent,
                'sent_at' => now()->subDays(3),
            ],
        ];

        foreach ($campaigns as $camp) {
            MarketingMessage::create([
                'country_id' => $camp['country']->id,
                'type' => $camp['type'],
                'title' => $camp['title'],
                'body' => $camp['body'],
                'redirect_url' => '/offers',
                'image' => '/images/lorempic.svg',
                'audience' => MarketingMessageAudience::All,
                'sent_to' => $camp['sent_to'],
                'open_count' => $camp['open_count'],
                'click_count' => $camp['click_count'],
                'open_rate' => $camp['sent_to'] > 0 ? round(($camp['open_count'] / $camp['sent_to']) * 100, 2) : 0,
                'clicks' => $camp['sent_to'] > 0 ? round(($camp['click_count'] / $camp['sent_to']) * 100, 2) : 0,
                'status' => $camp['status'],
                'scheduled_at' => $camp['scheduled_at'] ?? null,
                'sent_at' => $camp['sent_at'] ?? null,
                'created_by' => $adminId,
            ]);
        }
    }

    private function createReferralRewards(array $users, array $bookings): void
    {
        $coupons = [];
        for ($i = 0; $i < 4; $i++) {
            $coupons[] = Coupon::create([
                'code' => 'REF-'.strtoupper(Str::random(6)),
                'type' => CouponType::Fixed,
                'value' => 25,
                'expires_at' => now()->addMonths(6),
                'user_id' => $users[$i % count($users)]->id,
            ]);
        }

        for ($i = 0; $i < 3; $i++) {
            $referrer = $users[$i];
            $referee = $users[$i + 3];
            $booking = $bookings[$i % count($bookings)];

            ReferralReward::create([
                'referrer_id' => $referrer->id,
                'referee_id' => $referee->id,
                'booking_id' => $booking->id,
                'referee_coupon_id' => $coupons[$i]->id,
                'referrer_coupon_id' => $coupons[$i + 1]->id,
                'status' => 'rewarded',
            ]);
        }
    }

    private function createNearbyPlaces(array $india, array $uae, array $usa): void
    {
        $categories = NearbyPlaceCategory::all();
        if ($categories->isEmpty()) {
            return;
        }

        $hospital = $categories->where('name', 'Hospital')->first() ?? $categories->first();
        $mall = $categories->where('name', 'Shopping Mall')->first() ?? $categories->first();
        $restaurant = $categories->where('name', 'Restaurant')->first() ?? $categories->first();
        $airport = $categories->where('name', 'Airport')->first() ?? $categories->first();

        // 1. Mumbai (India)
        if ($india && isset($india['city'])) {
            $mumbaiCityId = $india['city']->id;

            NearbyPlace::create([
                'city_id' => $mumbaiCityId,
                'nearby_place_category_id' => $airport->id,
                'google_place_id' => 'ch_mumbai_airport',
                'name' => 'Chhatrapati Shivaji Maharaj International Airport',
                'latitude' => 19.0896,
                'longitude' => 72.8656,
                'address' => 'Vile Parle East, Mumbai, Maharashtra 400099',
                'distance_km' => 15.40,
                'rating' => 4.5,
            ]);

            NearbyPlace::create([
                'city_id' => $mumbaiCityId,
                'nearby_place_category_id' => $mall->id,
                'google_place_id' => 'ch_phoenix_mall',
                'name' => 'Phoenix Palladium Mall',
                'latitude' => 18.9940,
                'longitude' => 72.8250,
                'address' => 'Senapati Bapat Marg, Lower Parel, Mumbai 400013',
                'distance_km' => 4.20,
                'rating' => 4.6,
            ]);

            NearbyPlace::create([
                'city_id' => $mumbaiCityId,
                'nearby_place_category_id' => $restaurant->id,
                'google_place_id' => 'ch_leopold_cafe',
                'name' => 'Leopold Cafe',
                'latitude' => 18.9232,
                'longitude' => 72.8315,
                'address' => 'Colaba Causeway, Mumbai, Maharashtra 400001',
                'distance_km' => 0.80,
                'rating' => 4.2,
            ]);

            NearbyPlace::create([
                'city_id' => $mumbaiCityId,
                'nearby_place_category_id' => $hospital->id,
                'google_place_id' => 'ch_bombay_hospital',
                'name' => 'Bombay Hospital & Medical Research Centre',
                'latitude' => 18.9405,
                'longitude' => 72.8282,
                'address' => 'Marine Lines, Mumbai, Maharashtra 400020',
                'distance_km' => 2.50,
                'rating' => 4.0,
            ]);
        }

        // 2. Ahmedabad (Gujarat, India)
        $ahmedabad = City::where('name', 'Ahmedabad')->first();
        if ($ahmedabad) {
            NearbyPlace::create([
                'city_id' => $ahmedabad->id,
                'nearby_place_category_id' => $airport->id,
                'google_place_id' => 'ch_amd_airport',
                'name' => 'Sardar Vallabhbhai Patel International Airport (AMD)',
                'latitude' => 23.0772,
                'longitude' => 72.6347,
                'address' => 'Hansol, Ahmedabad, Gujarat 380003',
                'distance_km' => 9.50,
                'rating' => 4.4,
            ]);

            NearbyPlace::create([
                'city_id' => $ahmedabad->id,
                'nearby_place_category_id' => $restaurant->id,
                'google_place_id' => 'ch_agashiye_amd',
                'name' => 'Agashiye (The House of MG)',
                'latitude' => 23.0258,
                'longitude' => 72.5873,
                'address' => 'Lal Darwaja, Ahmedabad, Gujarat 380001',
                'distance_km' => 3.20,
                'rating' => 4.5,
            ]);

            NearbyPlace::create([
                'city_id' => $ahmedabad->id,
                'nearby_place_category_id' => $mall->id,
                'google_place_id' => 'ch_ahmedabad_one',
                'name' => 'Ahmedabad One Mall',
                'latitude' => 23.0382,
                'longitude' => 72.5170,
                'address' => 'Vastrapur, Ahmedabad, Gujarat 380054',
                'distance_km' => 5.40,
                'rating' => 4.4,
            ]);

            NearbyPlace::create([
                'city_id' => $ahmedabad->id,
                'nearby_place_category_id' => $hospital->id,
                'google_place_id' => 'ch_civil_amd',
                'name' => 'Civil Hospital Ahmedabad',
                'latitude' => 23.0530,
                'longitude' => 72.6010,
                'address' => 'Asarwa, Ahmedabad, Gujarat 380016',
                'distance_km' => 4.10,
                'rating' => 4.0,
            ]);
        }

        // 3. Bhuj (Gujarat, India)
        $bhuj = City::where('name', 'Bhuj')->first();
        if ($bhuj) {
            NearbyPlace::create([
                'city_id' => $bhuj->id,
                'nearby_place_category_id' => $airport->id,
                'google_place_id' => 'ch_bhuj_airport',
                'name' => 'Bhuj Airport (BHJ)',
                'latitude' => 23.2877,
                'longitude' => 69.6701,
                'address' => 'Bhuj, Gujarat 370001',
                'distance_km' => 4.50,
                'rating' => 4.0,
            ]);

            NearbyPlace::create([
                'city_id' => $bhuj->id,
                'nearby_place_category_id' => $restaurant->id,
                'google_place_id' => 'ch_prince_bhuj',
                'name' => 'Hotel Prince Restaurant',
                'latitude' => 23.2419,
                'longitude' => 69.6669,
                'address' => 'Station Road, Bhuj, Gujarat 370001',
                'distance_km' => 1.20,
                'rating' => 4.2,
            ]);

            NearbyPlace::create([
                'city_id' => $bhuj->id,
                'nearby_place_category_id' => $mall->id,
                'google_place_id' => 'ch_shroff_bazaar_bhuj',
                'name' => 'Shroff Bazaar',
                'latitude' => 23.2530,
                'longitude' => 69.6695,
                'address' => 'Shroff Bazaar, Bhuj, Gujarat 370001',
                'distance_km' => 0.80,
                'rating' => 4.1,
            ]);

            NearbyPlace::create([
                'city_id' => $bhuj->id,
                'nearby_place_category_id' => $hospital->id,
                'google_place_id' => 'ch_gk_general_bhuj',
                'name' => 'G. K. General Hospital',
                'latitude' => 23.2540,
                'longitude' => 69.6750,
                'address' => 'Hospital Road, Bhuj, Gujarat 370001',
                'distance_km' => 1.50,
                'rating' => 4.0,
            ]);
        }

        // 5. Dubai (UAE)
        if ($uae && isset($uae['city'])) {
            $dubaiCityId = $uae['city']->id;

            NearbyPlace::create([
                'city_id' => $dubaiCityId,
                'nearby_place_category_id' => $airport->id,
                'google_place_id' => 'ch_dxb_airport',
                'name' => 'Dubai International Airport (DXB)',
                'latitude' => 25.2532,
                'longitude' => 55.3657,
                'address' => 'Department of Civil Aviation, Dubai',
                'distance_km' => 12.10,
                'rating' => 4.7,
            ]);

            NearbyPlace::create([
                'city_id' => $dubaiCityId,
                'nearby_place_category_id' => $mall->id,
                'google_place_id' => 'ch_dubai_mall',
                'name' => 'The Dubai Mall',
                'latitude' => 25.1972,
                'longitude' => 55.2797,
                'address' => 'Financial Centre Road, Downtown Dubai',
                'distance_km' => 1.50,
                'rating' => 4.8,
            ]);

            NearbyPlace::create([
                'city_id' => $dubaiCityId,
                'nearby_place_category_id' => $restaurant->id,
                'google_place_id' => 'ch_al_maharah',
                'name' => 'Al Mahara Restaurant',
                'latitude' => 25.1412,
                'longitude' => 55.1852,
                'address' => 'Burj Al Arab, Jumeirah St, Dubai',
                'distance_km' => 8.30,
                'rating' => 4.6,
            ]);

            NearbyPlace::create([
                'city_id' => $dubaiCityId,
                'nearby_place_category_id' => $hospital->id,
                'google_place_id' => 'ch_dubai_hospital',
                'name' => 'Dubai Hospital',
                'latitude' => 25.2974,
                'longitude' => 55.3182,
                'address' => 'Al Khaleej St, Deira, Dubai',
                'distance_km' => 9.70,
                'rating' => 4.1,
            ]);
        }

        // 6. Abu Dhabi (UAE)
        $abuDhabi = City::where('name', 'Abu Dhabi')->first();
        if ($abuDhabi) {
            NearbyPlace::create([
                'city_id' => $abuDhabi->id,
                'nearby_place_category_id' => $airport->id,
                'google_place_id' => 'ch_auh_airport',
                'name' => 'Zayed International Airport (AUH)',
                'latitude' => 24.4279,
                'longitude' => 54.6511,
                'address' => 'Abu Dhabi, United Arab Emirates',
                'distance_km' => 32.00,
                'rating' => 4.6,
            ]);

            NearbyPlace::create([
                'city_id' => $abuDhabi->id,
                'nearby_place_category_id' => $restaurant->id,
                'google_place_id' => 'ch_zuma_ad',
                'name' => 'Zuma Restaurant Abu Dhabi',
                'latitude' => 24.5028,
                'longitude' => 54.3879,
                'address' => 'The Galleria, Al Maryah Island, Abu Dhabi',
                'distance_km' => 2.50,
                'rating' => 4.7,
            ]);

            NearbyPlace::create([
                'city_id' => $abuDhabi->id,
                'nearby_place_category_id' => $mall->id,
                'google_place_id' => 'ch_yas_mall',
                'name' => 'Yas Mall',
                'latitude' => 24.4880,
                'longitude' => 54.6074,
                'address' => 'Yas Island, Abu Dhabi',
                'distance_km' => 28.00,
                'rating' => 4.5,
            ]);

            NearbyPlace::create([
                'city_id' => $abuDhabi->id,
                'nearby_place_category_id' => $hospital->id,
                'google_place_id' => 'ch_cleveland_clinic',
                'name' => 'Cleveland Clinic Abu Dhabi',
                'latitude' => 24.5015,
                'longitude' => 54.3890,
                'address' => 'Al Maryah Island, Abu Dhabi',
                'distance_km' => 2.10,
                'rating' => 4.8,
            ]);
        }

        // 7. New York City (USA)
        if ($usa && isset($usa['city'])) {
            $newYorkCityId = $usa['city']->id;

            NearbyPlace::create([
                'city_id' => $newYorkCityId,
                'nearby_place_category_id' => $airport->id,
                'google_place_id' => 'ch_jfk_airport',
                'name' => 'John F. Kennedy International Airport (JFK)',
                'latitude' => 40.6413,
                'longitude' => -73.7781,
                'address' => 'Queens, NY 11430',
                'distance_km' => 24.50,
                'rating' => 4.2,
            ]);

            NearbyPlace::create([
                'city_id' => $newYorkCityId,
                'nearby_place_category_id' => $mall->id,
                'google_place_id' => 'ch_macys_herald',
                'name' => "Macy's Herald Square",
                'latitude' => 40.7510,
                'longitude' => -73.9890,
                'address' => '151 W 34th St, New York, NY 10001',
                'distance_km' => 1.20,
                'rating' => 4.5,
            ]);

            NearbyPlace::create([
                'city_id' => $newYorkCityId,
                'nearby_place_category_id' => $restaurant->id,
                'google_place_id' => 'ch_katz_deli',
                'name' => "Katz's Delicatessen",
                'latitude' => 40.7223,
                'longitude' => -73.9874,
                'address' => '205 E Houston St, New York, NY 10002',
                'distance_km' => 4.30,
                'rating' => 4.6,
            ]);

            NearbyPlace::create([
                'city_id' => $newYorkCityId,
                'nearby_place_category_id' => $hospital->id,
                'google_place_id' => 'ch_nyu_langone',
                'name' => 'NYU Langone Health',
                'latitude' => 40.7421,
                'longitude' => -73.9740,
                'address' => '550 1st Ave, New York, NY 10016',
                'distance_km' => 2.80,
                'rating' => 4.3,
            ]);
        }
    }

    private function createUserQueries(array $users): void
    {
        $queries = [
            [
                'name' => 'Raj Malhotra',
                'email' => 'raj.m@example.com',
                'subject' => 'Corporate booking query',
                'message' => 'Hello, I want to book 15 deluxe rooms for a corporate conference next month. Do you offer group discounts?',
                'status' => UserQueryStatus::Pending,
            ],
            [
                'name' => 'Aisha Khan',
                'email' => 'aisha@example.com',
                'subject' => 'Swimming pool maintenance',
                'message' => 'Is the swimming pool at Palm Paradise - Mumbai open during July? I heard it might be closed for maintenance.',
                'status' => UserQueryStatus::Reviewed,
            ],
            [
                'name' => 'Steve Smith',
                'email' => 'steve.s@example.com',
                'subject' => 'Invoice request',
                'message' => 'Please share the GST invoice for my booking BKG-X8J39D1S. I need it to claim business travel expenses.',
                'status' => UserQueryStatus::Resolved,
            ],
            [
                'name' => 'John Doe',
                'email' => 'john.doe@example.com',
                'subject' => 'Late check-out query',
                'message' => 'Is it possible to request a late check-out at Palm Paradise - Dubai? My flight is at 8:00 PM.',
                'status' => UserQueryStatus::Pending,
            ],
        ];

        foreach ($queries as $idx => $q) {
            $user = $users[$idx % count($users)];
            UserQuery::create([
                'query_number' => 'QRY-'.strtoupper(Str::random(8)),
                'user_id' => $user->id,
                'name' => $q['name'],
                'email' => $q['email'],
                'dial_code' => '+91',
                'phone' => '98765'.rand(10000, 99999),
                'subject' => $q['subject'],
                'message' => $q['message'],
                'status' => $q['status'],
            ]);
        }
    }

    private function createPropertyRules(): void
    {
        $rule1 = PropertyRule::firstOrCreate(
            ['name' => 'House Rules'],
            [
                'icon' => 'property-rules/rules-house.png',
                'description' => 'Basic regulations to ensure a pleasant stay for everyone.',
                'status' => PropertyRuleStatus::Active,
            ]
        );

        $rule1->questions()->firstOrCreate(
            ['question_text' => 'Are pets allowed?'],
            [
                'answer_type' => AnswerType::YesNo,
                'options' => null,
                'sort_order' => 1,
            ]
        );

        $rule1->questions()->firstOrCreate(
            ['question_text' => 'Is smoking permitted indoors?'],
            [
                'answer_type' => AnswerType::YesNo,
                'options' => null,
                'sort_order' => 2,
            ]
        );

        $rule2 = PropertyRule::firstOrCreate(
            ['name' => 'Guest Occupancy & Visitors'],
            [
                'icon' => 'property-rules/rules-occupancy.png',
                'description' => 'Guidelines regarding guest count and visitor access.',
                'status' => PropertyRuleStatus::Active,
            ]
        );

        $rule2->questions()->firstOrCreate(
            ['question_text' => 'Are overnight visitors allowed without registering?'],
            [
                'answer_type' => AnswerType::YesNo,
                'options' => null,
                'sort_order' => 1,
            ]
        );

        $rule2->questions()->firstOrCreate(
            ['question_text' => 'What is the maximum visitor stay duration?'],
            [
                'answer_type' => AnswerType::SingleSelect,
                'options' => ['2 hours', '4 hours', 'No limit'],
                'sort_order' => 2,
            ]
        );
    }

    private function createRegistrationFields(int $indiaId, int $uaeId, int $usaId): void
    {
        $countries = [$indiaId, $uaeId, $usaId];
        foreach ($countries as $countryId) {
            RegistrationField::firstOrCreate(
                ['country_id' => $countryId, 'name' => 'GSTIN / VAT Registration Number'],
                [
                    'property_type_id' => 1,
                    'field_type' => RegistrationFieldType::TextField,
                    'is_mandatory' => true,
                    'status' => Status::Active,
                    'max_length' => 15,
                ]
            );

            RegistrationField::firstOrCreate(
                ['country_id' => $countryId, 'name' => 'Business License Document'],
                [
                    'property_type_id' => 1,
                    'field_type' => RegistrationFieldType::FileUpload,
                    'is_mandatory' => true,
                    'status' => Status::Active,
                    'max_file_size' => 5,
                ]
            );

            RegistrationField::firstOrCreate(
                ['country_id' => $countryId, 'name' => 'Number of Floors'],
                [
                    'property_type_id' => 1,
                    'field_type' => RegistrationFieldType::NumberInput,
                    'is_mandatory' => false,
                    'status' => Status::Active,
                    'min_number' => 1,
                    'max_number' => 100,
                ]
            );
        }
    }

    private function createCurrencies(): void
    {
        Currency::firstOrCreate(
            ['currency_code' => 'INR'],
            [
                'currency_name' => 'Indian Rupee',
                'currency_symbol' => '₹',
                'country_name' => 'India',
                'country_iso2' => 'in',
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        Currency::firstOrCreate(
            ['currency_code' => 'AED'],
            [
                'currency_name' => 'UAE Dirham',
                'currency_symbol' => 'د.إ',
                'country_name' => 'United Arab Emirates',
                'country_iso2' => 'ae',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        Currency::firstOrCreate(
            ['currency_code' => 'USD'],
            [
                'currency_name' => 'US Dollar',
                'currency_symbol' => '$',
                'country_name' => 'United States',
                'country_iso2' => 'us',
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 3,
            ]
        );

        Currency::firstOrCreate(
            ['currency_code' => 'EUR'],
            [
                'currency_name' => 'Euro',
                'currency_symbol' => '€',
                'country_name' => 'European Union',
                'country_iso2' => 'eu',
                'is_default' => false,
                'is_active' => false,
                'sort_order' => 4,
            ]
        );
    }

    private function createManualRefundRequests(array $bookings): void
    {
        $cancelledBookings = array_filter($bookings, fn ($b) => $b->status === BookingStatus::Cancelled);
        $idx = 0;
        foreach ($cancelledBookings as $booking) {
            ManualRefundRequest::create([
                'user_id' => $booking->user_id,
                'booking_id' => $booking->id,
                'ref_id' => 'REF-'.strtoupper(Str::random(8)),
                'account_holder_name' => $booking->guest_name,
                'bank_name' => $this->profileByCurrency($booking->currency_code)['bank'],
                'account_number' => '987654321098'.$idx,
                'ifsc_swift_code' => $this->profileByCurrency($booking->currency_code)['swift'],
                'amount' => $booking->total_amount,
                'message' => 'Please refund the amount to my bank account.',
                'status' => $idx === 0 ? ManualRefundStatus::PendingReview : ManualRefundStatus::Transferred,
                'transaction_id' => $idx === 0 ? null : 'TXN-'.strtoupper(Str::random(10)),
                'transfer_reference_id' => $idx === 0 ? null : 'TRF-'.strtoupper(Str::random(10)),
                'transferred_at' => $idx === 0 ? null : now(),
            ]);
            $idx++;
            if ($idx >= 3) {
                break;
            }
        }
    }

    private function createRolesAndStaff(array $properties): void
    {
        // ── 1. Create Spatie Roles ─────────────────────────────────────────────
        $managerRole = Role::firstOrCreate(
            ['name' => 'Property Manager', 'guard_name' => 'web'],
            ['description' => 'Manages properties, bookings, and customer reviews.']
        );

        $supportRole = Role::firstOrCreate(
            ['name' => 'Support Agent', 'guard_name' => 'web'],
            ['description' => 'Manages user queries, FAQs, and refunds.']
        );

        $permissions = Permission::all();
        if ($permissions->isNotEmpty()) {
            $managerPerms = $permissions->filter(
                fn ($p) => str_starts_with($p->name, 'all-properties.') ||
                    str_starts_with($p->name, 'all-bookings.') ||
                    str_starts_with($p->name, 'reviews-ratings.')
            );
            $managerRole->syncPermissions($managerPerms);

            $supportPerms = $permissions->filter(
                fn ($p) => str_starts_with($p->name, 'faq-management.') ||
                    str_starts_with($p->name, 'user-queries.') ||
                    str_starts_with($p->name, 'manual-refunds.')
            );
            $supportRole->syncPermissions($supportPerms);
        }

        // ── 2. Create Staff Members ───────────────────────────────────────────
        $indiaProperty = collect($properties)->first(fn ($p) => $p->country_id === 1);
        $uaeProperty = collect($properties)->first(fn ($p) => $p->country_id === 2);
        $usaProperty = collect($properties)->first(fn ($p) => $p->country_id === 3);

        $staff1 = User::firstOrCreate(
            ['email' => 'rahul@estay.com'],
            [
                'name' => 'Rahul Sharma',
                'first_name' => 'Rahul',
                'last_name' => 'Sharma',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::Staff,
                'status' => UserStatus::Active,
                'country_id' => 1,
                'branch_id' => $indiaProperty?->id,
                'phone' => '9876501234',
                'state_province' => 'Maharashtra',
            ]
        );
        $staff1->roles()->sync([$managerRole->id]);

        $staff2 = User::firstOrCreate(
            ['email' => 'john.s@estay.com'],
            [
                'name' => 'John Smith',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::Staff,
                'status' => UserStatus::Active,
                'country_id' => 2,
                'branch_id' => $uaeProperty?->id,
                'phone' => '0501234568',
                'state_province' => 'Dubai',
            ]
        );
        $staff2->roles()->sync([$supportRole->id]);

        $staff3 = User::firstOrCreate(
            ['email' => 'emily.d@estay.com'],
            [
                'name' => 'Emily Davis',
                'first_name' => 'Emily',
                'last_name' => 'Davis',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'role' => UserRole::Staff,
                'status' => UserStatus::Active,
                'country_id' => 3,
                'branch_id' => $usaProperty?->id,
                'phone' => '2125550199',
                'state_province' => 'New York',
            ]
        );
        $staff3->roles()->sync([$managerRole->id]);

        // ── 3. Create Demo Admin (publicly shared account) ────────────────────
        // branch_id is intentionally null — this signals "full-access staff"
        // which allows country/property switching in the topbar like a real admin.
        $demoAdmin = User::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'name' => 'Demo Admin',
                'first_name' => 'Demo',
                'last_name' => 'Admin',
                'password' => Hash::make('admin@123'),
                'email_verified_at' => now(),
                'role' => UserRole::Staff,
                'status' => UserStatus::Active,
                'country_id' => 1,
                'branch_id' => null,
                'phone' => '9000000000',
                'state_province' => 'Maharashtra',
            ]
        );

        // All view/create/edit permissions (no delete).
        // general-settings: view only.
        $demoAdminPerms = $permissions->filter(function ($p) {
            if (str_starts_with($p->name, 'general-settings.')) {
                return $p->name === 'general-settings.view';
            }

            return ! str_ends_with($p->name, '.delete');
        });

        $demoAdmin->syncPermissions($demoAdminPerms);
    }

    /**
     * Single source of truth for per-country locale data (currency, tax, dial
     * code, refund bank). Keyed by Country id: 1 = India, 2 = UAE, 3 = USA.
     *
     * @return array<int, array{code: string, symbol: string, taxRate: float, taxName: string, dialCode: string, bank: string, swift: string}>
     */
    private function countryProfiles(): array
    {
        return [
            1 => ['code' => 'INR', 'symbol' => '₹', 'taxRate' => 0.18, 'taxName' => 'GST', 'dialCode' => '+91', 'bank' => 'State Bank of India', 'swift' => 'SBIN0001234'],
            2 => ['code' => 'AED', 'symbol' => 'د.إ', 'taxRate' => 0.05, 'taxName' => 'VAT', 'dialCode' => '+971', 'bank' => 'Emirates NBD', 'swift' => 'EMIRAEHHXXX'],
            3 => ['code' => 'USD', 'symbol' => '$', 'taxRate' => 0.08, 'taxName' => 'Sales Tax', 'dialCode' => '+1', 'bank' => 'Chase Bank', 'swift' => 'CHASUS33XXX'],
        ];
    }

    /**
     * @return array{code: string, symbol: string, taxRate: float, taxName: string, dialCode: string, bank: string, swift: string}
     */
    private function countryProfile(int $countryId): array
    {
        return $this->countryProfiles()[$countryId];
    }

    /**
     * @return array{code: string, symbol: string, taxRate: float, taxName: string, dialCode: string, bank: string, swift: string}
     */
    private function profileByCurrency(string $currencyCode): array
    {
        foreach ($this->countryProfiles() as $profile) {
            if ($profile['code'] === $currencyCode) {
                return $profile;
            }
        }

        return $this->countryProfile(1);
    }

    /**
     * Set created_at/updated_at to a past date without triggering model events
     * or allowing auto-timestamp to overwrite the value.
     */
    private function backdateModel(Model $model, Carbon $date): void
    {
        $model->timestamps = false;
        $model->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();
        $model->timestamps = true;
    }

    /**
     * Return a realistic booking creation date: 1–30 days before check-in.
     * Capped at today so future check-ins don't produce a future created_at.
     */
    private function bookingCreatedAt(Carbon $checkIn): Carbon
    {
        $createdAt = (clone $checkIn)->subDays(rand(1, 30));

        return $createdAt->isFuture() ? Carbon::now()->subDays(rand(0, 2)) : $createdAt;
    }

    private function seedMoreCitiesAndStates(Country $india, Country $uae): void
    {
        // 1. India States & Cities (Mumbai already seeded via setupCountry)
        $indiaStates = [
            'Gujarat' => ['Ahmedabad', 'Bhuj'],
        ];

        foreach ($indiaStates as $stateName => $cities) {
            $refState = RefState::where('country_id', $india->ref_country_id)->where('name', $stateName)->first();
            if ($refState) {
                $state = State::firstOrCreate(
                    ['ref_state_id' => $refState->id],
                    [
                        'country_id' => $india->id,
                        'name' => $refState->name,
                        'latitude' => $refState->latitude,
                        'longitude' => $refState->longitude,
                        'is_active' => true,
                    ]
                );

                foreach ($cities as $cityName) {
                    $refCity = RefCity::where('state_id', $refState->id)->where('name', $cityName)->first();
                    if ($refCity) {
                        City::firstOrCreate(
                            ['ref_city_id' => $refCity->id],
                            [
                                'country_id' => $india->id,
                                'state_id' => $state->id,
                                'name' => $refCity->name,
                                'latitude' => $refCity->latitude,
                                'longitude' => $refCity->longitude,
                                'status' => CityStatus::Active,
                            ]
                        );
                    }
                }
            }
        }

        // 2. UAE States & Cities (Dubai already seeded via setupCountry)
        $uaeStates = [
            'Abu Dhabi' => ['Abu Dhabi'],
        ];

        foreach ($uaeStates as $stateName => $cities) {
            $refState = RefState::where('country_id', $uae->ref_country_id)->where('name', $stateName)->first();
            if ($refState) {
                $state = State::firstOrCreate(
                    ['ref_state_id' => $refState->id],
                    [
                        'country_id' => $uae->id,
                        'name' => $refState->name,
                        'latitude' => $refState->latitude,
                        'longitude' => $refState->longitude,
                        'is_active' => true,
                    ]
                );

                foreach ($cities as $cityName) {
                    $refCity = RefCity::where('state_id', $refState->id)->where('name', $cityName)->first();
                    if ($refCity) {
                        City::firstOrCreate(
                            ['ref_city_id' => $refCity->id],
                            [
                                'country_id' => $uae->id,
                                'state_id' => $state->id,
                                'name' => $refCity->name,
                                'latitude' => $refCity->latitude,
                                'longitude' => $refCity->longitude,
                                'status' => CityStatus::Active,
                            ]
                        );
                    }
                }
            }
        }
    }
}
