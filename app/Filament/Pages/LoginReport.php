<?php

namespace App\Filament\Pages;

use App\Enums\LoginStatus;
use App\Enums\UserRole;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\HasReportPageConventions;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Contracts\IsReportPage;
use App\Models\LoginLog;
use App\Models\RefCountry;
use App\Models\Setting;
use App\Support\SystemMode;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Stevebauman\Location\Facades\Location;

class LoginReport extends Page implements DeclaresTopbarControls, HasTable, IsReportPage
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use HasReportPageConventions;
    use InteractsWithTable;

    protected static ?string $slug = 'reports/login';

    protected static string $permissionSlug = 'reports';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.report-table-page';

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function topbarControls(): array
    {
        // Logins aren't scoped to a property or a single country, so neither switcher applies.
        return ['country' => false, 'property' => false];
    }

    public function getTitle(): string|Htmlable
    {
        return __('admin.report_login_title');
    }

    public function getSubheading(): ?string
    {
        return __('admin.report_login_desc');
    }

    private function userIdColumnState(LoginLog $record): Htmlable
    {
        // No matched user (e.g. a mistyped/unknown email on a failed attempt) — there's no ID to
        // format, so show what was actually typed instead of a meaningless zero-padded placeholder.
        if (! $record->user_id) {
            return new HtmlString(e($record->identifier));
        }

        $label = '#'.str_pad((string) $record->user_id, 3, '0', STR_PAD_LEFT);

        if (! $record->user) {
            return new HtmlString(e($label));
        }

        $url = match ($record->user->role) {
            UserRole::Customer => CustomerView::getUrl(['record' => $record->user_id]),
            UserRole::Partner => $record->user->partner ? AllPartnersDetail::getUrl().'?partnerId='.$record->user->partner->id : null,
            default => null,
        };

        $linkedLabel = $url ? static::linkedNameWithIcon($url, $label) : new HtmlString(e($label));

        $name = $record->user->name ?? $record->user->email;
        $nameHtml = $name ? '<div class="text-xs text-gray-500 dark:text-gray-400 truncate" style="max-width: 160px;">'.e($name).'</div>' : '';

        return new HtmlString('<div>'.$linkedLabel.$nameHtml.'</div>');
    }

    /**
     * Renders the login timestamp via Alpine's x-text, converted in the browser from the UTC ISO
     * value rather than server-side — same "get browser time" convention already used for audit
     * log timestamps in all-partners-detail.blade.php. Date and time are two separate x-data spans
     * (stacked, time muted) to match the mockup's two-line layout.
     */
    private function loginDateTimeState(Carbon $dateTime): Htmlable
    {
        $iso = $dateTime->utc()->toIso8601String();

        $date = '<span x-data x-text="new Date(\''.$iso.'\').toLocaleDateString(\'en-US\', { day: \'numeric\', month: \'short\', year: \'numeric\' })"></span>';
        $time = '<span x-data x-text="new Date(\''.$iso.'\').toLocaleTimeString(\'en-US\', { hour: \'2-digit\', minute: \'2-digit\', second: \'2-digit\' })"></span>';

        return new HtmlString('<div>'.$date.'<div class="text-xs text-gray-500 dark:text-gray-400">'.$time.'</div></div>');
    }

    public static function resolveLocationFromIp(?string $ip): ?string
    {
        if (blank($ip) || in_array($ip, ['127.0.0.1', '::1'], true)) {
            return null;
        }

        return Cache::remember("ip_loc:{$ip}", 86400 * 7, function () use ($ip): ?string {
            try {
                if ($token = Setting::get('ipinfo_api_key')) {
                    config(['location.ipinfo.token' => $token]);
                }

                $position = Location::get($ip);
                if (! $position || ! $position->countryCode) {
                    return null;
                }

                $countryCode = strtoupper($position->countryCode);
                $countryName = $position->countryName ?: RefCountry::query()->where('iso2', $countryCode)->value('name') ?: $countryCode;

                $flag = '';
                if (strlen($countryCode) === 2) {
                    $flag = mb_chr(mb_ord($countryCode[0]) - 65 + 0x1F1E6).mb_chr(mb_ord($countryCode[1]) - 65 + 0x1F1E6).' ';
                }

                $text = $flag.$countryName;
                if ($position->cityName) {
                    $text .= ' ('.$position->cityName.')';
                }

                return $text;
            } catch (\Throwable) {
                return null;
            }
        });
    }

    public function table(Table $table): Table
    {
        $query = LoginLog::query()->with('user.partner');

        return $table
            ->query($query)
            ->defaultSort('created_at', 'desc')
            ->searchPlaceholder(__('admin.search_by_user_id_or_email'))
            ->columns([
                TextColumn::make('user_id')
                    ->label(__('admin.user_id'))
                    ->state(fn (LoginLog $record): Htmlable => $this->userIdColumnState($record))
                    // Customer/Partner rows render a real navigation link inside this cell (see
                    // userIdColumnState()) — copyable() intercepts the whole cell's click for
                    // clipboard, which stops that link from ever being reachable. Only enabled
                    // where there's nothing to navigate to (Admin/Staff/unmatched identifier).
                    ->copyable(fn (LoginLog $record): bool => ! in_array($record->user?->role, [UserRole::Customer, UserRole::Partner], true))
                    ->copyableState(fn (LoginLog $record): string => $record->user_id ? (string) $record->user_id : (string) $record->identifier)
                    ->searchable(['identifier', 'user.first_name', 'user.last_name', 'user.email']),

                TextColumn::make('role')
                    ->label(__('admin.role'))
                    ->badge()
                    ->formatStateUsing(fn (?UserRole $state): string => $state?->label() ?? '-')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('admin.login_date_time'))
                    ->state(fn (LoginLog $record): Htmlable => $this->loginDateTimeState($record->created_at))
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->label(__('admin.ip_address'))
                    ->html()
                    ->state(function (LoginLog $record): Htmlable {
                        $ip = e($record->ip_address ?? '-');
                        $location = $record->formattedLocation() ?? static::resolveLocationFromIp($record->ip_address);
                        $locationHtml = $location
                            ? '<div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">'.e($location).'</div>'
                            : '';

                        return new HtmlString('<div><span>'.$ip.'</span>'.$locationHtml.'</div>');
                    })
                    ->searchable(['ip_address', 'country_name', 'city'])
                    ->copyable()
                    ->copyableState(fn (LoginLog $record): ?string => $record->ip_address)
                    ->toggleable(),

                TextColumn::make('device')
                    ->label(__('admin.device'))
                    ->formatStateUsing(fn (?string $state): string => $state ?? '-')
                    ->toggleable(),

                TextColumn::make('user_agent')
                    ->label(__('admin.user_agent'))
                    ->formatStateUsing(fn (?string $state): string => $state ?? '-')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (LoginStatus $state): string => $state->label())
                    ->description(fn (LoginLog $record): ?string => $record->reason?->label())
                    ->color(fn (LoginStatus $state): string => $state->color())
                    ->sortable(),
            ])
            ->filters($filters = [
                static::dateRangeFilter('created_at'),

                SelectFilter::make('role')
                    ->label(__('admin.role'))
                    ->options(collect(UserRole::cases())->mapWithKeys(fn (UserRole $r) => [$r->value => $r->label()]))
                    ->native(false),

                SelectFilter::make('country_name')
                    ->label(__('admin.country'))
                    ->options(fn () => LoginLog::query()->whereNotNull('country_name')->distinct()->pluck('country_name', 'country_name')->toArray())
                    ->searchable()
                    ->native(false),

                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options(collect(LoginStatus::cases())->mapWithKeys(fn (LoginStatus $s) => [$s->value => $s->label()]))
                    ->native(false),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(count($filters))
            ->deferFilters(false)
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('login-report')
                    ->exports([
                        'user_id' => ['label' => 'User ID', 'formatter' => fn (LoginLog $r): string => '#'.str_pad((string) ($r->user_id ?? '-'), 3, '0', STR_PAD_LEFT)],
                        'user_name' => ['label' => 'User Name/Email', 'formatter' => fn (LoginLog $r): string => $r->user?->name ?? $r->user?->email ?? $r->identifier ?? '-'],
                        'role' => ['label' => 'Role', 'formatter' => fn (LoginLog $r): string => $r->role?->label() ?? '-'],
                        // No browser to convert this in — the exported file is a static download.
                        // Stays UTC, unlike the on-screen column's browser-local x-text rendering.
                        'created_at' => ['label' => 'Login Date & Time (UTC)', 'formatter' => fn (LoginLog $r): string => $r->created_at->utc()->format('d M Y h:i:s A')],
                        'ip_address' => ['label' => 'IP Address', 'formatter' => fn (LoginLog $r): string => $r->ip_address ?? '-'],
                        'location' => ['label' => 'Country / Location', 'formatter' => fn (LoginLog $r): string => $r->formattedLocation() ?? static::resolveLocationFromIp($r->ip_address) ?? '-'],
                        'device' => ['label' => 'Device', 'formatter' => fn (LoginLog $r): string => $r->device ?? '-'],
                        'user_agent' => ['label' => 'User Agent', 'formatter' => fn (LoginLog $r): string => $r->user_agent ?? '-'],
                        'status' => ['label' => 'Status', 'formatter' => fn (LoginLog $r): string => $r->status->label()],
                        'reason' => ['label' => 'Reason', 'formatter' => fn (LoginLog $r): string => $r->reason?->label() ?? '-'],
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.report_login_title'))
            ->emptyStateIcon('heroicon-o-arrow-right-on-rectangle')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions(static::reportPaginationOptions());
    }
}
