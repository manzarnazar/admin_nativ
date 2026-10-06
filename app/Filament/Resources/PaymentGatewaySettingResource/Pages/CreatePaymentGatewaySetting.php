<?php

namespace App\Filament\Resources\PaymentGatewaySettingResource\Pages;

use App\Filament\Pages\SystemSettings;
use App\Filament\Resources\PaymentGatewaySettingResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePaymentGatewaySetting extends CreateRecord
{
    protected static string $resource = PaymentGatewaySettingResource::class;

    public function getBreadcrumbs(): array
    {
        return [];

        // return [
        //     SystemSettings::getUrl() => __('admin.system_settings'),
        //     PaymentGatewaySettingResource::getUrl() => __('admin.payment_gateway_settings'),
        //     '#' => __('admin.create'),
        // ];
    }
}
