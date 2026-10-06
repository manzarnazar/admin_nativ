<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\PaymentGatewaySettingResource\Pages\CreatePaymentGatewaySetting;
use App\Filament\Resources\PaymentGatewaySettingResource\Pages\EditPaymentGatewaySetting;
use App\Filament\Resources\PaymentGatewaySettingResource\Pages\ListPaymentGatewaySettings;
use App\Filament\Resources\PaymentGatewaySettingResource\Schemas\PaymentGatewaySettingForm;
use App\Filament\Resources\PaymentGatewaySettingResource\Tables\PaymentGatewaySettingsTable;
use App\Models\PaymentGatewaySetting;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class PaymentGatewaySettingResource extends Resource implements DeclaresTopbarControls
{
    use HasResourcePermission;

    protected static ?string $model = PaymentGatewaySetting::class;

    protected static ?string $slug = 'payment-gateway-settings';

    protected static bool $shouldRegisterNavigation = true;

    /**
     * Gateway settings are scoped by the selected country — keep country, hide property.
     *
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.payment_gateway_settings');
    }

    public static function getModelLabel(): string
    {
        return __('admin.payment_gateway_settings');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.payment_gateway_settings');
    }

    protected static ?int $navigationSort = 4;

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    public static function form(Schema $schema): Schema
    {
        return PaymentGatewaySettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentGatewaySettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentGatewaySettings::route('/'),
            'create' => CreatePaymentGatewaySetting::route('/create'),
            'edit' => EditPaymentGatewaySetting::route('/{record}/edit'),
        ];
    }
}
