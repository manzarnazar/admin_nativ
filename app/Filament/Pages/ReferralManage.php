<?php

namespace App\Filament\Pages;

use App\Enums\CouponType;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Coupon;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ReferralManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission, InteractsWithTable;

    protected static ?string $slug = 'refer-earn';

    #[Url(as: 'tab')]
    public string $activeTab = 'referee';

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['country' => false, 'property' => false];
    }

    public static function getNavigationIcon(): string|\BackedEnum|Htmlable|null
    {
        return 'heroicon-o-megaphone';
    }

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.referral-manage';

    public function getTitle(): string|Htmlable
    {
        return __('admin.refer_earn_management');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.refer_earn');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Marketing);
    }

    // ── Tab Management ───────────────────────────────────────────────────────

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetTable();
    }

    // ── Stats & Data ─────────────────────────────────────────────────────────

    public function getReferralStats(): array
    {
        return [
            // Count trashed referees too so the stat matches the referral_rewards table
            // (which shows historical referrals even after a referee deletes their account).
            'total_referrals' => User::withTrashed()->whereNotNull('referred_by')->count(),
            'successful_referrals' => ReferralReward::where('status', 'success')->count(),
            'total_rewards_count' => ReferralReward::whereNotNull('referrer_coupon_id')->count(),
        ];
    }

    public function getReferralSettings(): array
    {
        $referrerExpiry = Setting::get('referral_referrer_expiry_days', 90);
        $refereeExpiry = Setting::get('referral_referee_expiry_days', 30);

        return [
            'enabled' => (bool) Setting::get('referral_enabled', true),
            'referrer_percentage' => Setting::get('referral_referrer_percentage', 10),
            'referrer_expiry' => (int) Setting::get('referral_referrer_expiry_days', 90) > 0 ? Setting::get('referral_referrer_expiry_days', 90).' '.__('admin.days') : __('admin.no_expiry'),
            'referee_percentage' => Setting::get('referral_referee_percentage', 15),
            'referee_expiry' => (int) Setting::get('referral_referee_expiry_days', 30) > 0 ? Setting::get('referral_referee_expiry_days', 30).' '.__('admin.days') : __('admin.no_expiry'),
        ];
    }

    // ── Actions ──────────────────────────────────────────────────────────────

    public function editRewardRulesAction(): Action
    {
        return Action::make('editRewardRules')
            ->label(__('admin.edit_reward_rules'))
            ->icon('heroicon-o-cog-6-tooth')
            ->color('gray')
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.edit_reward_rules'))
            ->modalWidth('2xl')
            ->fillForm(function () {
                return [
                    'referral_enabled' => Setting::get('referral_enabled', true),
                    'referral_referrer_percentage' => Setting::get('referral_referrer_percentage', 10),
                    'referral_referrer_expiry_is_expirable' => (int) Setting::get('referral_referrer_expiry_days', 90) > 0 ? '1' : '0',
                    'referral_referrer_expiry_days' => Setting::get('referral_referrer_expiry_days', 90),
                    'referral_referee_percentage' => Setting::get('referral_referee_percentage', 15),
                    'referral_referee_expiry_is_expirable' => (int) Setting::get('referral_referee_expiry_days', 30) > 0 ? '1' : '0',
                    'referral_referee_expiry_days' => Setting::get('referral_referee_expiry_days', 30),
                ];
            })
            ->form([
                Toggle::make('referral_enabled')
                    ->label(__('admin.program_status'))
                    ->helperText(__('admin.referral_enabled_helper'))
                    ->default(true),

                TextEntry::make('reason')
                    ->label('')
                    ->state(__('admin.referral_rules_placeholder'))
                    ->extraAttributes(['class' => 'p-4 bg-warning-50 text-warning-700 rounded-lg text-sm']),

                Section::make(__('admin.referrer_settings_title'))
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->schema([
                        TextInput::make('referral_referrer_percentage')
                            ->label(__('admin.reward_percentage'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                        Select::make('referral_referrer_expiry_is_expirable')
                            ->label(__('admin.is_expirable'))
                            ->options([
                                '0' => __('admin.expirable_no'),
                                '1' => __('admin.expirable_yes'),
                            ])
                            ->live()
                            ->required(),
                        TextInput::make('referral_referrer_expiry_days')
                            ->label(__('admin.expiry_days_label'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(500)
                            ->visible(fn ($get) => $get('referral_referrer_expiry_is_expirable'))
                            ->required(),
                    ]),

                Section::make(__('admin.referee_settings_title'))
                    ->icon('heroicon-o-user-plus')
                    ->columns(3)
                    ->schema([
                        TextInput::make('referral_referee_percentage')
                            ->label(__('admin.reward_percentage'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('%')
                            ->required(),
                        Select::make('referral_referee_expiry_is_expirable')
                            ->label(__('admin.is_expirable'))
                            ->options([
                                '0' => __('admin.expirable_no'),
                                '1' => __('admin.expirable_yes'),
                            ])
                            ->live()
                            ->required(),
                        TextInput::make('referral_referee_expiry_days')
                            ->label(__('admin.expiry_days_label'))
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(500)
                            ->visible(fn ($get) => $get('referral_referee_expiry_is_expirable'))
                            ->required(),
                    ]),
            ])
            ->action(function (array $data) {
                Setting::set('referral_enabled', $data['referral_enabled']);
                Setting::set('referral_referrer_percentage', $data['referral_referrer_percentage']);

                $referrerDays = $data['referral_referrer_expiry_is_expirable'] ? $data['referral_referrer_expiry_days'] : 0;
                Setting::set('referral_referrer_expiry_days', $referrerDays);

                Setting::set('referral_referee_percentage', $data['referral_referee_percentage']);

                $refereeDays = $data['referral_referee_expiry_is_expirable'] ? $data['referral_referee_expiry_days'] : 0;
                Setting::set('referral_referee_expiry_days', $refereeDays);

                Notification::make()
                    ->title(__('admin.referral_settings_updated'))
                    ->success()
                    ->send();
            });
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ReferralReward::query()->with([
                // referrer/referee already include trashed via the model relations
                'referrer', 'referee', 'refereeCoupon', 'referrerCoupon', 'booking',
            ]))
            ->searchPlaceholder(__('admin.search_by_referrer_and_date'))
            ->columns($this->getTableColumns())
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options([
                        'success' => __('admin.status_completed'),
                        'pending' => __('admin.status_pending'),
                        'expired' => __('admin.status_expire'),
                    ]),

                SelectFilter::make('has_booking')
                    ->label(__('admin.booking'))
                    ->options([
                        'yes' => __('admin.yes'),
                        'no' => __('admin.no'),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        if (! filled($data['value'] ?? null)) {
                            return;
                        }

                        $data['value'] === 'yes'
                            ? $query->whereNotNull('booking_id')
                            : $query->whereNull('booking_id');
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(10);
    }

    /**
     * Render the customer cell as bold name + a clickable "ID - XXXX" link below in primary blue,
     * matching the Figma. Appends "(Account deleted)" when the user is soft-deleted.
     */
    private function renderUserCell(?int $userId, ?User $user): string
    {
        $name = e($user?->name ?? '—');
        if ($user?->trashed()) {
            $name .= ' <span style="color:#dc2626;font-size:0.75rem;">('.e(__('admin.account_deleted')).')</span>';
        }
        $idLabel = 'ID - '.str_pad((string) ($userId ?? 0), 4, '0', STR_PAD_LEFT);
        $idHtml = $userId
            ? '<a href="'.e(CustomerView::getUrl(['record' => $userId])).'" class="hover:underline" style="color:#2563eb;font-size:0.75rem;">'.e($idLabel).'</a>'
            : '<span style="color:#6b7280;font-size:0.75rem;">'.e($idLabel).'</span>';

        return '<div style="font-weight:700;color:#111827;">'.$name.'</div><div style="margin-top:2px;">'.$idHtml.'</div>';
    }

    /**
     * Render the column-5 cell: coupon discount on top, clickable BK-XXXX link below.
     * Empty state ("--") when no booking has been made yet (Figma-strict).
     */
    private function renderAmountBookingCell(ReferralReward $record, ?Coupon $coupon): string
    {
        if (! $record->booking_id) {
            return '<span style="color:#9ca3af;">--</span>';
        }

        $valueHtml = '';
        if ($coupon?->value) {
            $formatted = $coupon->type === CouponType::Percentage
                ? rtrim(rtrim(number_format($coupon->value, 2, '.', ''), '0'), '.').'%'
                : ($record->booking?->currency_symbol ?? '$').number_format($coupon->value, 2);
            $valueHtml = '<div style="font-weight:600;color:#111827;">'.e($formatted).'</div>';
        }

        $bk = $record->booking?->booking_number ?? ('BK-'.str_pad((string) $record->booking_id, 4, '0', STR_PAD_LEFT));
        $bookingHtml = '<a href="'.e(BookingView::getUrl(['record' => $record->booking_id])).'" class="hover:underline" style="color:#2563eb;font-size:0.75rem;">'.e($bk).'</a>';

        return $valueHtml.$bookingHtml;
    }

    private function getTableColumns(): array
    {
        if ($this->activeTab === 'referee') {
            return [
                // 1. REFERRER — name + linked ID
                TextColumn::make('referrer.name')
                    ->label(__('admin.referrer'))
                    ->html()
                    ->formatStateUsing(fn ($record) => $this->renderUserCell($record->referrer_id, $record->referrer))
                    ->searchable(),

                // 2. REFERRAL CODE
                TextColumn::make('referrer.referral_code')
                    ->label(__('admin.referral_code'))
                    ->html()
                    ->formatStateUsing(fn ($state) => $state
                        ? '<span style="background:#111827;color:#fff;font-weight:700;padding:3px 10px;border-radius:6px;font-size:12px;letter-spacing:0.5px;display:inline-block;white-space:nowrap;">'.$state.'</span>'
                        : '--')
                    ->toggleable(),

                // 3. REFEREE (NEW USER) — name + linked ID
                TextColumn::make('referee.name')
                    ->label(__('admin.referee_new_user'))
                    ->html()
                    ->formatStateUsing(fn ($record) => $this->renderUserCell($record->referee_id, $record->referee))
                    ->searchable(),

                // 4. REFEREE COUPON
                TextColumn::make('refereeCoupon.code')
                    ->label(__('admin.referee_coupon'))
                    ->placeholder('--')
                    ->weight('semibold'),

                // 5. BOOKING ID — coupon discount on top, BK link below
                TextColumn::make('booking_id')
                    ->label(__('admin.booking_id'))
                    ->html()
                    // getStateUsing replaces state resolution entirely — runs unconditionally
                    // even when booking_id is null, unlike formatStateUsing which can short-circuit.
                    ->getStateUsing(fn ($record) => $this->renderAmountBookingCell($record, $record->refereeCoupon))
                    ->toggleable(),

                // 6. STATUS
                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'success' => 'success',
                        'pending' => 'warning',
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'success' => __('admin.status_completed'),
                        'pending' => __('admin.status_pending'),
                        'expired' => __('admin.status_expire'),
                        default => ucfirst($state),
                    })
                    ->toggleable(),

                // 7. REFERRED DATE
                TextColumn::make('created_at')
                    ->label(__('admin.referred_date'))
                    ->dateTime('d M Y')
                    ->toggleable(),
            ];
        }

        // ── Referrer Rewards Tab ──────────────────────────────────────────
        // Column order per Figma: REFEREE first, REFERRER third (swapped from Tab 1).

        return [
            // 1. REFEREE (NEW USER) — name + linked ID
            TextColumn::make('referee.name')
                ->label(__('admin.referee_new_user'))
                ->html()
                ->formatStateUsing(fn ($record) => $this->renderUserCell($record->referee_id, $record->referee))
                ->searchable(),

            // 2. REFERRAL CODE
            TextColumn::make('referrer.referral_code')
                ->label(__('admin.referral_code'))
                ->html()
                ->formatStateUsing(fn ($state) => $state
                    ? '<span style="background:#111827;color:#fff;font-weight:700;padding:3px 10px;border-radius:6px;font-size:12px;letter-spacing:0.5px;display:inline-block;white-space:nowrap;">'.$state.'</span>'
                    : '--')
                ->toggleable(),

            // 3. REFERRER — name + linked ID
            TextColumn::make('referrer.name')
                ->label(__('admin.referrer'))
                ->html()
                ->formatStateUsing(fn ($record) => $this->renderUserCell($record->referrer_id, $record->referrer))
                ->searchable(),

            // 4. REFERRER COUPON
            TextColumn::make('referrerCoupon.code')
                ->label(__('admin.referrer_coupon'))
                ->placeholder('--')
                ->weight('semibold'),

            // 5. DISCOUNT DETAILS — referrer's reward amount on top, BK link below
            TextColumn::make('booking_id')
                ->label(__('admin.discount_details'))
                ->html()
                // getStateUsing replaces state resolution entirely — runs unconditionally
                // even when booking_id is null, unlike formatStateUsing which can short-circuit.
                ->getStateUsing(fn ($record) => $this->renderAmountBookingCell($record, $record->referrerCoupon))
                ->toggleable(),

            // 6. STATUS
            TextColumn::make('status')
                ->label(__('admin.status'))
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'success' => 'success',
                    'pending' => 'warning',
                    'expired' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn ($state) => match ($state) {
                    'success' => __('admin.status_completed'),
                    'pending' => __('admin.status_pending'),
                    'expired' => __('admin.status_expire'),
                    default => ucfirst($state),
                })
                ->toggleable(),

            // 7. REFERRED DATE
            TextColumn::make('created_at')
                ->label(__('admin.referred_date'))
                ->dateTime('d M Y')
                ->toggleable(),
        ];
    }
}
