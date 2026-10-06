<?php

namespace Tests\Unit\Services;

use App\Enums\CouponType;
use App\Models\Coupon;
use App\Services\Api\CouponService;
use Tests\TestCase;

class CouponServiceTest extends TestCase
{
    private CouponService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CouponService;
    }

    public function test_calculate_discount_computes_percentage_of_total(): void
    {
        $coupon = new Coupon(['type' => CouponType::Percentage, 'value' => 10]);

        $this->assertSame(100.0, $this->service->calculateDiscount($coupon, 1000));
    }

    public function test_calculate_discount_returns_fixed_value(): void
    {
        $coupon = new Coupon(['type' => CouponType::Fixed, 'value' => 150]);

        $this->assertSame(150.0, $this->service->calculateDiscount($coupon, 1000));
    }

    public function test_calculate_discount_is_capped_at_the_total_amount(): void
    {
        $coupon = new Coupon(['type' => CouponType::Fixed, 'value' => 500]);

        $this->assertSame(200.0, $this->service->calculateDiscount($coupon, 200));
    }

    public function test_calculate_discount_rounds_to_two_decimals(): void
    {
        $coupon = new Coupon(['type' => CouponType::Percentage, 'value' => 33.333]);

        $this->assertSame(33.33, $this->service->calculateDiscount($coupon, 100));
    }
}
