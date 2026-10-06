<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\PartnerVerificationStatus;
use App\Enums\PropertyStatus;
use App\Filament\Concerns\HasAdminDemoGuard;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\ResolvesBackUrl;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Models\Country;
use App\Models\Partner;
use App\Services\PartnerVerificationService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Spatie\Activitylog\Models\Activity;

class AllPartnersDetail extends Page implements DeclaresTopbarControls
{
    use HasAdminDemoGuard;
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithFormActions;
    use ResolvesBackUrl;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'all-partners-detail';

    protected string $view = 'filament.pages.all-partners-detail';

    #[Url]
    public int $partnerId = 0;

    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    public ?Partner $partner = null;

    public ?string $backUrl = null;

    public ?string $auditLogDate = null;

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    /**
     * @return array{country?: bool, property?: bool}
     */
    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function mount(): void
    {
        if (! $this->partnerId) {
            $this->redirect(AllPartnersManage::getUrl());

            return;
        }

        $this->partner = Partner::query()
            ->with(['user', 'countries', 'registrationValues.registrationField'])
            ->findOrFail($this->partnerId);

        $this->backUrl = $this->resolveBackUrl(AllPartnersManage::getUrl());
    }

    public function getTitle(): string|Htmlable
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function setAuditLogDate(string $date): void
    {
        $this->auditLogDate = $date ?: null;
    }

    public function getProperties(): Collection
    {
        if (! $this->partner) {
            return collect();
        }

        return $this->partner->properties()
            ->with(['country', 'refCity', 'refState', 'primaryImages'])
            ->get();
    }

    public function getDocuments(): array
    {
        if (! $this->partner) {
            return [];
        }

        return $this->partner->registrationValues
            ->filter(fn ($v) => $v->registrationField !== null && $v->registrationField->field_type->value === 'file_upload')
            ->flatMap(function ($regVal) {
                $files = is_array($regVal->value) ? $regVal->value : [$regVal->value];

                return collect(array_filter((array) $files))->map(fn ($file) => [
                    'name' => $regVal->registrationField->name,
                    'path' => $file,
                    'uploaded_at' => $regVal->updated_at,
                ]);
            })
            ->values()
            ->toArray();
    }

    public function getVerifiedByName(): string
    {
        if (! $this->partner || $this->partner->verification_status !== PartnerVerificationStatus::Approved) {
            return '—';
        }

        $log = Activity::query()
            ->where('subject_type', Partner::class)
            ->where('subject_id', $this->partner->id)
            ->where('event', 'approved')
            ->latest()
            ->with('causer')
            ->first();

        return $log?->causer?->name ?? __('admin.admin');
    }

    public function getAuditLogs(): Collection
    {
        $query = Activity::query()
            ->where('subject_type', Partner::class)
            ->where('subject_id', $this->partner?->id)
            ->with('causer')
            ->latest();

        if ($this->auditLogDate) {
            $query->whereDate('created_at', $this->auditLogDate);
        }

        return $query->limit(50)->get();
    }

    public function getOverviewStats(): array
    {
        if (! $this->partner) {
            return ['totalProperties' => 0, 'activeProperties' => 0, 'totalBookings' => 0, 'totalRevenue' => 0, 'currencySymbol' => '$'];
        }

        $countryId = Auth::user()?->current_country_id;

        $propertiesBase = $this->partner->properties()
            ->when($countryId, fn (Builder $q) => $q->where('country_id', $countryId));

        $totalProperties = (clone $propertiesBase)->count();
        $activeProperties = (clone $propertiesBase)->where('status', PropertyStatus::Active->value)->count();

        $activeStatuses = [BookingStatus::Confirmed, BookingStatus::CheckedIn, BookingStatus::Completed];

        $bookingsBase = $this->partner->bookings()
            ->whereIn('bookings.status', $activeStatuses)
            ->when($countryId, fn (Builder $q) => $q->where('properties.country_id', $countryId));

        $totalBookings = (clone $bookingsBase)->count();
        $totalRevenue = (clone $bookingsBase)->sum('total_amount');

        $currencySymbol = Country::query()
            ->where('id', $countryId)
            ->value('currency_symbol') ?? '$';

        return compact('totalProperties', 'activeProperties', 'totalBookings', 'totalRevenue', 'currencySymbol');
    }

    public function toggleSuspensionAction(): Action
    {
        $isSuspended = $this->partner?->verification_status === PartnerVerificationStatus::Suspended;

        return Action::make('toggleSuspension')
            ->label($isSuspended ? __('admin.unsuspend_partner') : __('admin.suspend_partner'))
            ->icon($isSuspended ? 'heroicon-o-arrow-path' : 'heroicon-o-no-symbol')
            ->color($isSuspended ? 'success' : 'danger')
            ->link()
            ->visible(fn (): bool => in_array($this->partner?->verification_status, [
                PartnerVerificationStatus::Approved,
                PartnerVerificationStatus::Suspended,
            ]))
            ->before($this->enforceRestrictedActionGuard())
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalWidth('2xl')
            ->modalHeading($isSuspended ? __('admin.unsuspend_partner') : 'Suspend Partner')
            ->modalContent($isSuspended ? null : view('filament.admin.modals.suspend-partner', ['partner' => $this->partner]))
            ->modalSubmitActionLabel($isSuspended ? __('admin.unsuspend_partner') : 'Suspend Partner')
            ->modalSubmitAction(fn ($action) => $action->color($isSuspended ? 'success' : 'danger'))
            ->modalFooterActionsAlignment(Alignment::End)
            ->form($isSuspended ? [] : [
                Textarea::make('reason')
                    ->label('Reason for Suspension')
                    ->placeholder('Please provide a reason for suspending this property...')
                    ->maxLength(500)
                    ->required()
                    ->hint(fn ($state) => 'Character Limit: '.strlen($state ?? '').' / 500')
                    ->live(debounce: 500),
            ])
            ->action(function (array $data): void {
                $verificationService = app(PartnerVerificationService::class);

                if ($this->partner->verification_status === PartnerVerificationStatus::Suspended) {
                    $verificationService->unsuspendPartner($this->partner);

                    Notification::make()
                        ->title(__('admin.partner_unsuspended_success'))
                        ->success()
                        ->send();

                    $this->partner->refresh();

                    return;
                }

                try {
                    $verificationService->suspendPartner($this->partner, $data['reason'] ?? '');
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

                $this->redirect(AllPartnersManage::getUrl());
            });
    }
}
