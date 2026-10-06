<?php

namespace App\Services\Payments\Providers;

use App\Models\PaymentGatewaySetting;
use App\Services\Payments\Contracts\PaymentProviderInterface;
use App\Services\Payments\Exceptions\PaymentGatewayException;

abstract class BasePaymentProvider implements PaymentProviderInterface
{
    protected ?PaymentGatewaySetting $settings = null;

    public function setSettings(PaymentGatewaySetting $settings): static
    {
        $this->settings = $settings;

        return $this;
    }

    protected function requireSettings(): PaymentGatewaySetting
    {
        if (! $this->settings) {
            throw new PaymentGatewayException('Payment gateway settings not configured.');
        }

        return $this->settings;
    }

    protected function isLive(): bool
    {
        return $this->requireSettings()->isLive();
    }
}
