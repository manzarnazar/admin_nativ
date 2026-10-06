<?php

namespace App\Services\Payments;

use App\Enums\PaymentGateway;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\Exceptions\PaymentGatewayException;
use App\Services\Payments\Providers\FlutterwaveProvider;
use App\Services\Payments\Providers\RazorpayProvider;
use App\Services\Payments\Providers\StripeProvider;

class PaymentProviderFactory
{
    /**
     * Map gateway enum/string to provider class.
     *
     * @var array<string, class-string<PaymentProviderInterface>>
     */
    protected static array $map = [
        'razorpay' => RazorpayProvider::class,
        'stripe' => StripeProvider::class,
        'flutterwave' => FlutterwaveProvider::class,
    ];

    /**
     * Create a provider instance for a given gateway type.
     * Optionally attach settings (credentials) for that country/gateway.
     */
    public static function make(PaymentGateway|string $gateway, ?PaymentGatewaySetting $settings = null): PaymentProviderInterface
    {
        $key = $gateway instanceof PaymentGateway ? $gateway->value : (string) $gateway;

        if (! isset(self::$map[$key])) {
            throw new PaymentGatewayException("Unsupported payment gateway: {$key}");
        }

        /** @var PaymentProviderInterface $provider */
        $provider = app(self::$map[$key]);

        if ($settings) {
            $provider->setSettings($settings);
        }

        return $provider;
    }

    /**
     * Create a provider with settings auto-loaded for a country.
     */
    public static function makeForCountry(PaymentGateway|string $gateway, int $countryId): PaymentProviderInterface
    {
        $key = $gateway instanceof PaymentGateway ? $gateway->value : (string) $gateway;

        $settings = PaymentGatewaySetting::query()
            ->where('gateway_type', $key)
            ->where('country_id', $countryId)
            ->where('is_active', true)
            ->first();

        if (! $settings) {
            throw new PaymentGatewayException("No active settings found for gateway '{$key}' and country {$countryId}.");
        }

        return self::make($gateway, $settings);
    }
}
