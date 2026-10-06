<?php

namespace App\Filament\Resources\PaymentGatewaySettingResource\Tables;

use App\Filament\Resources\PaymentGatewaySettingResource;
use App\Support\UserTimezone;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentGatewaySettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('gateway_type')
                    ->label(__('admin.gateway'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('country.name')
                    ->label(__('admin.country'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('mode')
                    ->label(__('admin.mode_label'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'test' => 'warning',
                        'live' => 'success',
                    })
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('admin.active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.created'))
                    ->formatStateUsing(function ($record): string {
                        return $record->created_at->setTimezone(UserTimezone::current())->format('M d, Y H:i').' '.UserTimezone::abbreviation();
                    })
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->disabled(PaymentGatewaySettingResource::disabledUnlessCanEdit()),
            ])
            ->modifyQueryUsing(fn ($query) => $query->where('country_id', auth()->user()->current_country_id))
            ->defaultSort('created_at', 'desc');
    }
}
