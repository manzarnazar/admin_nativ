<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasResourcePermission;
use App\Filament\Enums\NavigationGroup;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\PaymentResource\Pages\ViewPayment;
use App\Filament\Resources\PaymentResource\Tables\PaymentsTable;
use App\Models\Payment;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

class PaymentResource extends Resource
{
    use HasResourcePermission;

    protected static ?string $model = Payment::class;

    protected static ?string $slug = 'payments';

    public static function getNavigationLabel(): string
    {
        return __('admin.payments');
    }

    public static function getModelLabel(): string
    {
        return __('admin.payment');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.payments');
    }

    protected static ?int $navigationSort = 7;

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return null;
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Settings);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
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
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
