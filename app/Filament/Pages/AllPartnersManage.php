<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\PartnerVerificationStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasAdminDemoGuard;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Partner;
use App\Models\User;
use App\Services\PartnerVerificationService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class AllPartnersManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasAdminDemoGuard;
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'all-partners';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.all-partners';

    public static function getNavigationLabel(): string
    {
        return __('admin.all_partners');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Partners);
    }

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.all_partners');
    }

    private function getCurrentCountryId(): ?int
    {
        /** @var User $user */
        $user = Auth::user();

        return $user?->current_country_id;
    }

    private function getCurrencySymbol(): string
    {
        return Country::query()
            ->where('id', $this->getCurrentCountryId())
            ->value('currency_symbol') ?? '$';
    }

    protected function getViewData(): array
    {
        $countryId = $this->getCurrentCountryId();

        $base = Partner::query()
            ->whereHas('user')
            ->when($countryId, fn (Builder $q) => $q->whereHas('countries', fn (Builder $c) => $c->where('countries.id', $countryId)));

        return [
            'totalPartners' => (clone $base)->count(),
            'activePartners' => (clone $base)->where('verification_status', PartnerVerificationStatus::Approved->value)->count(),
            'inactivePartners' => (clone $base)->whereNotIn('verification_status', [
                PartnerVerificationStatus::Approved->value,
                PartnerVerificationStatus::Suspended->value,
            ])->count(),
            'suspendedPartners' => (clone $base)->where('verification_status', PartnerVerificationStatus::Suspended->value)->count(),
            'currencySymbol' => $this->getCurrencySymbol(),
        ];
    }

    public function table(Table $table): Table
    {
        $countryId = $this->getCurrentCountryId();

        $activeStatuses = [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed];

        $revenueSubquery = Booking::query()
            ->selectRaw('COALESCE(SUM(total_amount), 0)')
            ->join('properties', 'properties.id', '=', 'bookings.property_id')
            ->whereColumn('properties.partner_id', 'partners.id')
            ->whereIn('bookings.status', $activeStatuses)
            ->when($countryId, fn (Builder $q) => $q->where('properties.country_id', $countryId));

        return $table
            ->query(
                Partner::query()
                    ->select('partners.*')
                    ->with([
                        'user',
                        'countries',
                        'propertyType',
                        'properties' => fn ($q) => $q
                            ->when($countryId, fn ($inner) => $inner->where('country_id', $countryId))
                            ->with('propertyType'),
                    ])
                    ->withCount([
                        'properties as properties_count' => fn (Builder $q) => $q->when(
                            $countryId,
                            fn (Builder $inner) => $inner->where('country_id', $countryId)
                        ),
                    ])
                    ->selectSub($revenueSubquery, 'country_revenue')
                    ->whereHas('user')
                    // Only partners approved at least once belong on this operating-roster
                    // page. Suspended is reachable only from Approved (see toggle_suspension's
                    // visibility check), so this pair alone means "approved before" — a
                    // partner still Pending/Rejected/CorrectionRequested/Resubmission has
                    // never been reviewed and belongs in the /partner-verification queue
                    // instead, not here.
                    ->whereIn('verification_status', [
                        PartnerVerificationStatus::Approved->value,
                        PartnerVerificationStatus::Suspended->value,
                    ])
                    ->when($countryId, fn (Builder $q) => $q->whereHas('countries', fn (Builder $c) => $c->where('countries.id', $countryId)))
                    ->latest()
            )
            ->columns([
                TextColumn::make('partner_info')
                    ->label(__('admin.partner_info'))
                    ->html()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', '%'.$search.'%')
                                ->orWhere('email', 'like', '%'.$search.'%');
                        });
                    })
                    ->state(function (Partner $record): Htmlable {
                        $av = $record->user?->avatar;
                        $avatarSrc = $av
                            ? (filter_var($av, FILTER_VALIDATE_URL) ? $av : asset('storage/'.$av))
                            : asset('avatars/defaultUser.svg');
                        $name = e($record->user?->name ?? '—');
                        $id = $record->id;

                        $ext = $av ? strtolower(pathinfo($av, PATHINFO_EXTENSION)) : 'jpg';

                        return new HtmlString('<div class="flex items-center gap-3">
                            <img src="'.e($avatarSrc).'"
                                @click="$dispatch(\'open-document-modal\', { url: \''.e($avatarSrc).'\', title: \''.$name.'\', subtitle: \'Profile Photo\', ext: \''.e($ext).'\' })"
                                class="h-9 w-9 flex-shrink-0 rounded-lg object-cover cursor-pointer hover:opacity-80 transition" />
                            <div>
                                <p class="font-medium text-gray-900 dark:text-white">'.$name.'</p>
                                <p class="text-xs text-primary-600 dark:text-primary-400">ID - '.$id.'</p>
                            </div>
                        </div>');
                    }),

                TextColumn::make('property_type')
                    ->label(__('admin.property_type'))
                    ->state(function (Partner $record): string {
                        if ($record->propertyType) {
                            return $record->propertyType->name;
                        }

                        $types = $record->properties
                            ->pluck('propertyType.name')
                            ->filter()
                            ->unique()
                            ->values();

                        return $types->isNotEmpty() ? $types->join(', ') : '—';
                    })
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('contact_info')
                    ->label(__('admin.contact_info'))
                    ->html()
                    ->state(function (Partner $record): string {
                        $email = e($record->user?->email ?? '—');
                        $phone = e(trim(($record->user?->dial_code ?? '').' '.($record->user?->phone ?? '')));

                        return '<div class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" /></svg>
                                <span>'.$email.'</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 flex-shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 6.75Z" /></svg>
                                <span>'.($phone ?: '—').'</span>
                            </div>
                        </div>';
                    }),

                TextColumn::make('properties_count')
                    ->label(__('admin.properties'))
                    ->weight(FontWeight::Medium)
                    ->alignCenter(),

                TextColumn::make('country_revenue')
                    ->label(__('admin.total_revenue'))
                    ->state(function (Partner $record): string {
                        static $symbol = null;
                        $symbol ??= $this->getCurrencySymbol();

                        return $symbol.number_format($record->country_revenue ?? 0, 2);
                    })
                    ->weight(FontWeight::Medium),

                TextColumn::make('verification_status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (PartnerVerificationStatus $state): string => match ($state) {
                        PartnerVerificationStatus::Approved => __('admin.active'),
                        PartnerVerificationStatus::Suspended => __('admin.suspended'),
                        default => __('admin.inactive'),
                    })
                    ->color(fn (PartnerVerificationStatus $state): string => match ($state) {
                        PartnerVerificationStatus::Approved => 'success',
                        PartnerVerificationStatus::Suspended => 'danger',
                        default => 'warning',
                    }),

                TextColumn::make('created_at')
                    ->label(__('admin.registered_on'))
                    ->date('d M Y'),
            ])
            ->recordActions([
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->url(fn (Partner $record): string => AllPartnersDetail::getUrl().'?partnerId='.$record->id),

                Action::make('toggle_suspension')
                    ->iconButton()
                    ->icon(fn (Partner $record): string => $record->verification_status === PartnerVerificationStatus::Approved
                        ? 'heroicon-o-exclamation-triangle'
                        : 'heroicon-o-arrow-path')
                    ->color('gray')
                    ->visible(fn (Partner $record): bool => in_array($record->verification_status, [
                        PartnerVerificationStatus::Approved,
                        PartnerVerificationStatus::Suspended,
                    ]))
                    ->before($this->enforceRestrictedActionGuard())
                    ->requiresConfirmation()
                    ->modalWidth('2xl')
                    ->modalHeading(fn (Partner $record): string => $record->verification_status === PartnerVerificationStatus::Approved
                        ? 'Suspend Partner'
                        : __('admin.unsuspend_partner'))
                    ->modalContent(fn (Partner $record) => $record->verification_status === PartnerVerificationStatus::Approved ? view('filament.admin.modals.suspend-partner', ['partner' => $record]) : null)
                    ->modalDescription(fn (Partner $record): ?string => $record->verification_status === PartnerVerificationStatus::Approved
                        ? null
                        : __('admin.unsuspend_partner_warning').' — '.$record->user?->name)
                    ->modalSubmitActionLabel(fn (Partner $record): string => $record->verification_status === PartnerVerificationStatus::Approved
                        ? 'Suspend Partner'
                        : __('admin.unsuspend_partner'))
                    ->modalSubmitAction(fn ($action, Partner $record) => $action->color($record->verification_status === PartnerVerificationStatus::Approved ? 'danger' : 'success'))
                    ->form(fn (Partner $record) => $record->verification_status === PartnerVerificationStatus::Approved ? [
                        Textarea::make('reason')
                            ->label('Reason for Suspension')
                            ->placeholder('Please provide a reason for suspending this property...')
                            ->maxLength(500)
                            ->required()
                            ->hint(fn ($state, $component) => 'Character Limit: '.strlen($state ?? '').' / 500')
                            ->live(debounce: 500),
                    ] : [])
                    ->action(function (Partner $record, array $data): void {
                        $verificationService = app(PartnerVerificationService::class);

                        if ($record->verification_status === PartnerVerificationStatus::Approved) {
                            try {
                                $verificationService->suspendPartner($record, $data['reason'] ?? '');
                            } catch (\InvalidArgumentException $e) {
                                Notification::make()
                                    ->title($e->getMessage())
                                    ->danger()
                                    ->send();

                                return;
                            }

                            Notification::make()
                                ->title(__('admin.partner_suspended_success'))
                                ->success()
                                ->send();

                            return;
                        }

                        $verificationService->unsuspendPartner($record);

                        Notification::make()
                            ->title(__('admin.partner_unsuspended_success'))
                            ->success()
                            ->send();
                    }),
            ])
            ->filters([
                SelectFilter::make('verification_status')
                    ->label(__('admin.status'))
                    ->options([
                        PartnerVerificationStatus::Approved->value => __('admin.active'),
                        PartnerVerificationStatus::Suspended->value => __('admin.suspended'),
                    ]),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('all-partners')
                    ->exports([
                        'user.name' => __('admin.partner'),
                        'user.email' => __('admin.email'),
                        'user.phone' => __('admin.phone'),
                        'verification_status' => [
                            'label' => __('admin.status'),
                            'formatter' => fn (Partner $record): string => match ($record->verification_status) {
                                PartnerVerificationStatus::Approved => __('admin.active'),
                                PartnerVerificationStatus::Suspended => __('admin.suspended'),
                                default => __('admin.inactive'),
                            },
                        ],
                        'created_at' => __('admin.registered_on'),
                    ])
                    ->toActionGroup(),
            ])
            ->searchPlaceholder(__('admin.search_partners'))
            ->emptyStateHeading(__('admin.no_partners_found'))
            ->emptyStateDescription('');
    }
}
