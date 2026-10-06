<?php

namespace Tests\Feature\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentGateway;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\BookingRoomAssignment;
use App\Models\Country;
use App\Models\Floor;
use App\Models\Payment;
use App\Models\Property;
use App\Models\PropertyRoom;
use App\Models\PropertyType;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Setting;
use App\Models\User;
use App\Services\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InvoiceService::class);
    }

    private function createBookingWithRelations(array $bookingOverrides = []): Booking
    {
        $country = Country::factory()->create(['currency_symbol' => '$', 'currency_code' => 'USD']);
        $propertyType = PropertyType::factory()->create(['is_active' => true]);
        $property = Property::factory()->create([
            'country_id' => $country->id,
            'property_type_id' => $propertyType->id,
            'name' => 'Juhu Beach Hotel',
            'phone' => '9000030005',
            'email' => 'bhavikbhuva81+juhu@gmail.com',
            'street_address' => 'Juhu Tara Road',
        ]);
        $roomType = RoomType::factory()->create();
        $propertyRoom = PropertyRoom::factory()->create([
            'property_id' => $property->id,
            'room_type_id' => $roomType->id,
        ]);
        $customer = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '1234567890',
        ]);

        $booking = Booking::factory()->create(array_merge([
            'property_id' => $property->id,
            'property_room_id' => $propertyRoom->id,
            'user_id' => $customer->id,
            'booking_number' => 'BK-1001',
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'guest_phone' => '1234567890',
            'guest_dial_code' => '+1',
            'check_in' => now()->addDays(2),
            'check_out' => now()->addDays(5),
            'total_nights' => 3,
            'adults' => 2,
            'children' => 0,
            'booked_rooms' => 1,
            'price_per_night' => 100.00,
            'base_amount' => 300.00,
            'tax_amount' => 30.00,
            'discount_amount' => 0.00,
            'total_amount' => 330.00,
            'tax_details' => [['name' => 'VAT', 'rate' => 10, 'amount' => 30.00, 'type' => 'percentage']],
            'payment_status' => PaymentStatus::Paid,
            'payment_method' => PaymentMethod::PayOnline,
            'status' => BookingStatus::Confirmed,
        ], $bookingOverrides));

        Payment::create([
            'booking_id' => $booking->id,
            'user_id' => $customer->id,
            'gateway_type' => PaymentGateway::Stripe,
            'amount' => 330.00,
            'currency' => 'USD',
            'status' => PaymentTransactionStatus::Success,
        ]);

        return $booking;
    }

    public function test_single_mode_view_data_and_pdf_generation(): void
    {
        Setting::set('system_mode', 'single');
        Setting::set('app_name', 'SingleHotel App');

        $booking = $this->createBookingWithRelations();

        $data = $this->service->buildViewData($booking);

        $this->assertFalse($data['isMulti']);
        $this->assertEquals('Juhu Beach Hotel', $data['property']->name);
        $this->assertEquals(330.00, $data['totalPaid']);

        $pdfContent = $this->service->generatePdf($booking);
        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF-', $pdfContent);
    }

    public function test_multi_mode_view_data_and_pdf_generation(): void
    {
        Setting::set('system_mode', 'multi');
        Setting::set('app_name', 'Estay Platform');
        Setting::set('contact_email', 'support@estay.com');
        Setting::set('contact_phone', '+1 800 123 4567');
        Setting::set('frontend_web_url', 'https://estay.example.com');

        $booking = $this->createBookingWithRelations();

        $data = $this->service->buildViewData($booking);

        $this->assertTrue($data['isMulti']);
        $this->assertEquals('Estay Platform', $data['platform']['name']);
        $this->assertEquals('support@estay.com', $data['platform']['email']);
        $this->assertEquals('+1 800 123 4567', $data['platform']['phone']);
        $this->assertEquals('https://estay.example.com', $data['platform']['website']);

        $pdfContent = $this->service->generatePdf($booking);
        $this->assertNotEmpty($pdfContent);
        $this->assertStringStartsWith('%PDF-', $pdfContent);
    }

    public function test_multi_room_assignments_are_formatted_in_view_data(): void
    {
        Setting::set('system_mode', 'single');

        $booking = $this->createBookingWithRelations(['booked_rooms' => 2]);

        $floor = Floor::create([
            'property_id' => $booking->property_id,
            'name' => 'Floor 1',
            'sort_order' => 1,
        ]);

        $room1 = Room::create([
            'property_id' => $booking->property_id,
            'floor_id' => $floor->id,
            'property_room_id' => $booking->property_room_id,
            'room_number' => '101',
        ]);
        $room2 = Room::create([
            'property_id' => $booking->property_id,
            'floor_id' => $floor->id,
            'property_room_id' => $booking->property_room_id,
            'room_number' => '102',
        ]);

        BookingRoomAssignment::create([
            'booking_id' => $booking->id,
            'room_id' => $room1->id,
            'assigned_at' => now(),
        ]);
        BookingRoomAssignment::create([
            'booking_id' => $booking->id,
            'room_id' => $room2->id,
            'assigned_at' => now(),
        ]);

        $data = $this->service->buildViewData($booking);

        $this->assertEquals('101, 102', $data['assignedRooms']);

        $pdfContent = $this->service->generatePdf($booking);
        $this->assertNotEmpty($pdfContent);
    }

    public function test_download_returns_correct_response(): void
    {
        $booking = $this->createBookingWithRelations();

        $response = $this->service->download($booking);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Invoice-BK-1001.pdf', $response->headers->get('Content-Disposition') ?? '');
    }
}
