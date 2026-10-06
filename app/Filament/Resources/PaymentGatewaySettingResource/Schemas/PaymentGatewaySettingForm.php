<?php

namespace App\Filament\Resources\PaymentGatewaySettingResource\Schemas;

use App\Enums\PaymentGateway;
use App\Models\PaymentGatewaySetting;
use App\Models\ProcessedWebhookEvent;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class PaymentGatewaySettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make(__('admin.gateway_configuration'))
                ->schema([
                    Select::make('country_id')
                        ->label(__('admin.country'))
                        ->relationship('country', 'name')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->default(fn (): ?int => auth()->user()?->current_country_id)
                        ->disabled()
                        ->dehydrated()
                        ->helperText(__('admin.gateway_country_helper'))
                        ->required(),
                    Select::make('gateway_type')
                        ->label(__('admin.gateway_type_label'))
                        ->disabled(fn (?Model $record) => $record !== null)
                        ->options(function (Get $get, ?Model $record = null): array {
                            $allGateways = [
                                'razorpay' => __('admin.razorpay'),
                                'stripe' => __('admin.stripe'),
                                'flutterwave' => __('admin.flutterwave'),
                            ];

                            $countryId = $get('country_id');

                            if (! $countryId) {
                                return $allGateways;
                            }

                            $usedGateways = PaymentGatewaySetting::query()
                                ->where('country_id', $countryId)
                                ->when($record?->id, fn ($q) => $q->where('id', '!=', $record->id))
                                ->pluck('gateway_type')
                                ->map(fn (PaymentGateway $gateway) => $gateway->value)
                                ->all();

                            return collect($allGateways)
                                ->reject(fn ($label, $key) => in_array($key, $usedGateways))
                                ->toArray();
                        })
                        ->live()
                        ->required(),
                    Select::make('mode')
                        ->label(__('admin.mode_label'))
                        ->options([
                            'test' => __('admin.test_mode'),
                            'live' => __('admin.live_mode'),
                        ])
                        ->default('test')
                        ->required(),
                    Toggle::make('is_active')
                        ->label(__('admin.active'))
                        ->default(true)
                        ->columnSpanFull(),
                ])
                ->columns(2),

            // Razorpay fields
            Section::make(__('admin.razorpay_credentials'))
                ->schema([
                    TextInput::make('api_key')
                        ->label(__('admin.key_id_label'))
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('api_secret')
                        ->label(__('admin.key_secret_label'))
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('webhook_secret')
                        ->label(__('admin.webhook_secret_label'))
                        ->password()
                        ->revealable()
                        ->nullable(),
                    TextInput::make('razorpay_payment_webhook_url')
                        ->label(__('admin.payment_webhook_url_label'))
                        ->formatStateUsing(fn (): string => url('/api/payments/webhook/razorpay'))
                        ->readOnly()
                        ->saved(false)
                        ->copyable(copyMessage: __('admin.copied'), copyMessageDuration: 1500)
                        ->helperText(new HtmlString(__('admin.razorpay_webhook_helper')))
                        ->columnSpanFull(),
                    TextInput::make('razorpay_refund_webhook_url')
                        ->label(__('admin.refund_webhook_url_label'))
                        ->formatStateUsing(fn (): string => url('/api/refunds/webhook/razorpay'))
                        ->readOnly()
                        ->saved(false)
                        ->copyable(copyMessage: __('admin.copied'), copyMessageDuration: 1500)
                        ->helperText(new HtmlString(__('admin.refund_webhook_helper')))
                        ->columnSpanFull(),
                    ...self::webhookStatusFields(),
                ])
                ->columns(2)
                ->visible(fn (Get $get) => $get('gateway_type') === PaymentGateway::Razorpay->value),

            // Stripe fields
            Section::make(__('admin.stripe_credentials'))
                ->schema([
                    TextInput::make('public_key')
                        ->label(__('admin.publishable_key_label'))
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('api_secret')
                        ->label(__('admin.secret_key_label'))
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('webhook_secret')
                        ->label(__('admin.webhook_secret_label'))
                        ->password()
                        ->revealable()
                        ->nullable(),
                    TextInput::make('stripe_payment_webhook_url')
                        ->label(__('admin.payment_webhook_url_label'))
                        ->formatStateUsing(fn (): string => url('/api/payments/webhook/stripe'))
                        ->readOnly()
                        ->saved(false)
                        ->copyable(copyMessage: __('admin.copied'), copyMessageDuration: 1500)
                        ->helperText(new HtmlString(__('admin.stripe_webhook_helper')))
                        ->columnSpanFull(),
                    ...self::webhookStatusFields(),
                ])
                ->columns(2)
                ->visible(fn (Get $get) => $get('gateway_type') === PaymentGateway::Stripe->value),

            // Flutterwave fields
            Section::make(__('admin.flutterwave_credentials'))
                ->schema([
                    TextInput::make('public_key')
                        ->label(__('admin.public_key_label'))
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('api_secret')
                        ->label(__('admin.secret_key_label'))
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('encryption_key')
                        ->label(__('admin.encryption_key_label'))
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('webhook_secret')
                        ->label(__('admin.secret_hash_label'))
                        ->password()
                        ->revealable()
                        ->required(),
                    TextInput::make('flutterwave_payment_webhook_url')
                        ->label(__('admin.payment_webhook_url_label'))
                        ->formatStateUsing(fn (): string => url('/api/payments/webhook/flutterwave'))
                        ->readOnly()
                        ->saved(false)
                        ->copyable(copyMessage: __('admin.copied'), copyMessageDuration: 1500)
                        ->helperText(new HtmlString(__('admin.flutterwave_webhook_helper')))
                        ->columnSpanFull(),
                    ...self::webhookStatusFields(),
                ])
                ->columns(2)
                ->visible(fn (Get $get) => $get('gateway_type') === PaymentGateway::Flutterwave->value),
        ]);
    }

    /**
     * One combined indicator (payment OR refund events, whichever is most recent) — the point is
     * just "is this gateway reaching us at all", not distinguishing event types. A refund-specific
     * split looked useful in theory but reads as a false alarm in practice: many refunds complete
     * synchronously from the gateway's immediate API response (see PaymentService::processRefund())
     * and never need a webhook at all, so "Refund Webhook Status: Not received yet" would show up
     * as the normal, everything-is-fine state just as often as it would show a real problem.
     *
     * @return array<int, TextInput>
     */
    private static function webhookStatusFields(): array
    {
        return [
            TextInput::make('webhook_status')
                ->label(__('admin.webhook_status_label'))
                ->formatStateUsing(fn (?Model $record) => self::webhookStatusText($record))
                ->extraInputAttributes(fn (?Model $record) => self::webhookStatusStyle($record))
                ->readOnly()
                ->saved(false)
                ->columnSpanFull()
                ->visible(fn (?Model $record) => $record !== null),
        ];
    }

    /**
     * Scoped to this exact settings row (payment_gateway_setting_id), not just gateway type —
     * a gateway can have multiple active settings rows (one per country) sharing the same
     * webhook URL, so filtering by gateway alone would show one country's traffic on another
     * country's settings page.
     */
    private static function lastWebhookEvent(?Model $record): ?ProcessedWebhookEvent
    {
        if (! $record) {
            return null;
        }

        return ProcessedWebhookEvent::where('payment_gateway_setting_id', $record->getKey())
            ->orderBy('processed_at', 'desc')
            ->first();
    }

    private static function webhookStatusText(?Model $record): string
    {
        $lastEvent = self::lastWebhookEvent($record);

        if ($lastEvent) {
            return __('admin.webhook_active_last_received', ['time' => $lastEvent->processed_at->diffForHumans()]);
        }

        return __('admin.webhook_not_received_yet');
    }

    /**
     * @return array<string, string>
     */
    private static function webhookStatusStyle(?Model $record): array
    {
        if (self::lastWebhookEvent($record)) {
            return ['style' => 'color: rgba(var(--success-600), 1); font-weight: 500;'];
        }

        return ['style' => 'color: rgba(var(--warning-600), 1); font-weight: 500;'];
    }
}
