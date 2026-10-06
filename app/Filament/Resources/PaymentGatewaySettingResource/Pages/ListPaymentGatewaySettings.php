<?php

namespace App\Filament\Resources\PaymentGatewaySettingResource\Pages;

use App\Filament\Resources\PaymentGatewaySettingResource;
use App\Models\PaymentGatewaySetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentGatewaySettings extends ListRecords
{
    protected static string $resource = PaymentGatewaySettingResource::class;

    protected function getHeaderActions(): array
    {
        $availableGatewayCount = 3; // razorpay, stripe, flutterwave
        $currentCountryId = auth()->user()->current_country_id;
        $configured = PaymentGatewaySetting::query()->where('country_id', $currentCountryId)->count();

        return [
            CreateAction::make()
                ->visible($configured < $availableGatewayCount)
                ->disabled(PaymentGatewaySettingResource::disabledUnlessCanCreate()),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
