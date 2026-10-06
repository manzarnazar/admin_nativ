<?php

namespace Tests\Feature\Payments;

use App\Models\PaymentGatewaySetting;
use App\Services\Payments\Exceptions\PaymentGatewayException;
use App\Services\Payments\Providers\FlutterwaveProvider;
use App\Services\Payments\Providers\RazorpayProvider;
use App\Services\Payments\Providers\StripeProvider;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit-style coverage for each gateway provider's getRefundStatus() mapping,
 * used by the refund reconciliation job. No database is touched (settings are
 * built in memory), so these run despite the SQLite migration blocker.
 */
class RefundStatusTest extends TestCase
{
    private function settings(): PaymentGatewaySetting
    {
        $settings = new PaymentGatewaySetting;
        $settings->api_key = 'key_test';
        $settings->api_secret = 'secret_test';

        return $settings;
    }

    public function test_stripe_maps_succeeded_to_completed(): void
    {
        Http::fake([
            'api.stripe.com/v1/refunds/*' => Http::response(['id' => 're_1', 'status' => 'succeeded'], 200),
        ]);

        $provider = (new StripeProvider)->setSettings($this->settings());

        $this->assertSame('completed', $provider->getRefundStatus('re_1')['status']);
    }

    public function test_stripe_maps_pending_to_processing(): void
    {
        Http::fake([
            'api.stripe.com/v1/refunds/*' => Http::response(['id' => 're_1', 'status' => 'pending'], 200),
        ]);

        $provider = (new StripeProvider)->setSettings($this->settings());

        $this->assertSame('processing', $provider->getRefundStatus('re_1')['status']);
    }

    public function test_stripe_maps_canceled_to_failed(): void
    {
        Http::fake([
            'api.stripe.com/v1/refunds/*' => Http::response(['id' => 're_1', 'status' => 'canceled'], 200),
        ]);

        $provider = (new StripeProvider)->setSettings($this->settings());

        $this->assertSame('failed', $provider->getRefundStatus('re_1')['status']);
    }

    public function test_razorpay_maps_processed_to_completed(): void
    {
        Http::fake([
            'api.razorpay.com/v1/refunds/*' => Http::response(['id' => 'rfnd_1', 'status' => 'processed'], 200),
        ]);

        $provider = (new RazorpayProvider)->setSettings($this->settings());

        $this->assertSame('completed', $provider->getRefundStatus('rfnd_1')['status']);
    }

    public function test_razorpay_maps_pending_to_processing(): void
    {
        Http::fake([
            'api.razorpay.com/v1/refunds/*' => Http::response(['id' => 'rfnd_1', 'status' => 'pending'], 200),
        ]);

        $provider = (new RazorpayProvider)->setSettings($this->settings());

        $this->assertSame('processing', $provider->getRefundStatus('rfnd_1')['status']);
    }

    public function test_flutterwave_matches_refund_in_transaction_list(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/transactions/*/refunds' => Http::response([
                'status' => 'success',
                'data' => [
                    ['id' => 111, 'status' => 'failed'],
                    ['id' => 222, 'status' => 'completed'],
                ],
            ], 200),
        ]);

        $provider = (new FlutterwaveProvider)->setSettings($this->settings());

        $this->assertSame('completed', $provider->getRefundStatus('222', '99999')['status']);
        $this->assertSame('failed', $provider->getRefundStatus('111', '99999')['status']);
    }

    public function test_flutterwave_unknown_refund_defaults_to_processing(): void
    {
        Http::fake([
            'api.flutterwave.com/v3/transactions/*/refunds' => Http::response([
                'status' => 'success',
                'data' => [],
            ], 200),
        ]);

        $provider = (new FlutterwaveProvider)->setSettings($this->settings());

        $this->assertSame('processing', $provider->getRefundStatus('999', '99999')['status']);
    }

    public function test_flutterwave_requires_transaction_id(): void
    {
        $provider = (new FlutterwaveProvider)->setSettings($this->settings());

        $this->expectException(PaymentGatewayException::class);

        $provider->getRefundStatus('222', null);
    }
}
