<?php

namespace App\Filament\Resources\PaymentGatewaySettingResource\Pages;

use App\Filament\Resources\PaymentGatewaySettingResource;
use App\Models\PaymentGatewaySetting;
use Filament\Resources\Pages\EditRecord;

class EditPaymentGatewaySetting extends EditRecord
{
    protected static string $resource = PaymentGatewaySettingResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $setting = PaymentGatewaySetting::find($record);

        if ($setting && $setting->country_id !== auth()->user()->current_country_id) {
            $this->redirect(PaymentGatewaySettingResource::getUrl('index'));

            return;
        }
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
