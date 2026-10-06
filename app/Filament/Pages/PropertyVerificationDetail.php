<?php

namespace App\Filament\Pages;

use App\Enums\PropertyVerificationStatus;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Concerns\ResolvesRuleAnswerLabels;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Mail\PropertyApprovedMailable;
use App\Mail\PropertyCorrectionRequestedMailable;
use App\Mail\PropertyRejectedMailable;
use App\Models\CancellationPolicy;
use App\Models\City;
use App\Models\FacilityCategory;
use App\Models\NearbyPlace;
use App\Models\Property;
use App\Models\PropertyRule;
use App\Services\CancellationPolicyService;
use App\Services\CommissionService;
use App\Services\NotificationService;
use App\Services\PropertyWalletService;
use App\Support\Geo;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

class PropertyVerificationDetail extends Page implements DeclaresTopbarControls
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithFormActions;
    use ResolvesRuleAnswerLabels;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'property-verification-detail';

    protected string $view = 'filament.pages.property-verification-detail';

    #[Url]
    public int $propertyId = 0;

    #[Url(as: 'tab')]
    public string $activeTab = 'overview';

    public ?Property $property = null;

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public static function topbarControls(): array
    {
        return ['property' => false];
    }

    public function mount(): void
    {
        if (! $this->propertyId) {
            $this->redirect(PropertyVerificationManage::getUrl());

            return;
        }

        $this->property = Property::query()
            ->with([
                'partner.user',
                'propertyType',
                'country',
                'refState',
                'refCity',
                'facilities.category',
                'rooms.roomType.facilities',
                'ruleAnswers.question.propertyRule',
                'primaryImages',
                'galleryImages',
                'registrationValues.registrationField',
            ])
            ->find($this->propertyId);

        if (! $this->property) {
            $this->redirect(PropertyVerificationManage::getUrl());
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->property?->name ?? __('admin.property_verification');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getProperty(): ?Property
    {
        return $this->property;
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // ── Verification Actions ────────────────────────────────────────────────

    /**
     * No commission rule = property cannot be approved — the platform must
     * never take a property live with 0% commission just because nothing was
     * configured for its country + property type.
     */
    private function propertyCommissionMissing(): bool
    {
        if (! $this->property) {
            return false;
        }

        $details = app(CommissionService::class)->resolveRateWithDetails(
            $this->property->country_id,
            $this->property->partner_id,
            $this->property->property_type_id ?? 0,
        );

        return $details['source'] === 'none';
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('admin.approve_property'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (): bool => $this->property !== null && $this->property->verification_status !== PropertyVerificationStatus::Approved)
            ->disabled(fn (): bool => $this->propertyCommissionMissing())
            ->tooltip(fn (): ?string => $this->propertyCommissionMissing()
                ? __('admin.property_commission_missing_tooltip')
                : null)
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-check-circle')
            ->modalIconColor('success')
            ->modalAlignment(Alignment::Center)
            ->modalHeading(__('admin.approve_property'))
            ->modalDescription(__('admin.approve_property_confirm'))
            ->modalSubmitActionLabel(__('admin.approve_property'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('success'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->action(function (): void {
                DB::transaction(function (): void {
                    app(PropertyWalletService::class)->firstOrCreateForProperty($this->property);

                    $this->property?->update([
                        'verification_status' => PropertyVerificationStatus::Approved,
                        'verified_by' => auth()->id(),
                        'verified_at' => now(),
                        'verification_notes' => null,
                    ]);
                });

                activity()
                    ->performedOn($this->property)
                    ->causedBy(auth()->user())
                    ->event('approved')
                    ->log('Property was approved');

                $partnerUser = $this->property?->partner?->user;
                if ($partnerUser?->email) {
                    Mail::to($partnerUser->email)->queue(new PropertyApprovedMailable($this->property));
                }

                if ($partnerUser?->id) {
                    $userLocale = $partnerUser->locale ?? app()->getLocale();
                    app(NotificationService::class)->send(
                        type: 'property_approved',
                        title: __('notifications.property_approved_title', [], $userLocale),
                        body: __('notifications.property_approved_body', ['property' => $this->property?->name ?? ''], $userLocale),
                        userIds: $partnerUser->id,
                        data: ['type' => 'property_approved', 'property_id' => $this->property?->id],
                    );
                }

                Notification::make()
                    ->title(__('admin.property_approved_success'))
                    ->success()
                    ->send();

                $this->redirect(PropertyVerificationManage::getUrl());
            });
    }

    public function requestCorrectionAction(): Action
    {
        return Action::make('requestCorrection')
            ->label(__('admin.request_correction'))
            ->color('gray')
            ->icon('heroicon-o-information-circle')
            ->visible(fn (): bool => $this->property !== null && $this->property->verification_status !== PropertyVerificationStatus::Approved)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.request_correction'))
            ->modalSubmitActionLabel(__('admin.send_correction_request'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('primary'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalCancelAction(fn (Action $action): Action => $action->link()->color('gray')->extraAttributes(['class' => '!text-gray-700 hover:!text-gray-900 dark:!text-gray-300']))
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema([
                TextEntry::make('partner_card')
                    ->hiddenLabel()
                    ->state(function (): HtmlString {
                        $user = $this->property?->partner?->user;
                        $avatar = $user?->avatar;
                        $avatarSrc = $avatar
                            ? (filter_var($avatar, FILTER_VALIDATE_URL) ? $avatar : asset('storage/'.$avatar))
                            : asset('avatars/defaultUser.svg');
                        $name = e($user?->name ?? '');

                        return new HtmlString(
                            '<div class="flex items-center gap-4 rounded-xl border border-gray-200/90 bg-white p-3.5 dark:border-gray-700 dark:bg-gray-800">'
                            .'<img src="'.$avatarSrc.'" alt="'.$name.'" class="h-12 w-12 flex-shrink-0 rounded-lg object-cover" />'
                            .'<span class="text-base font-bold text-gray-900 dark:text-white">'.$name.'</span>'
                            .'</div>'
                        );
                    }),
                Textarea::make('reason')
                    ->label(__('admin.reason_for_correction'))
                    ->placeholder('Mention what needs correction so the partner can update and resubmit.')
                    ->required()
                    ->maxLength(500)
                    ->rows(4)
                    ->helperText('Character Limit: 0 / 500'),
            ])
            ->action(function (array $data): void {
                $this->property?->update([
                    'verification_status' => PropertyVerificationStatus::CorrectionRequested,
                    'verification_notes' => $data['reason'],
                ]);

                activity()
                    ->performedOn($this->property)
                    ->causedBy(auth()->user())
                    ->event('correction_requested')
                    ->withProperties(['reason' => $data['reason']])
                    ->log('Correction requested from partner');

                $partnerUser = $this->property?->partner?->user;
                if ($partnerUser?->email) {
                    Mail::to($partnerUser->email)->queue(new PropertyCorrectionRequestedMailable($this->property, $data['reason']));
                }

                if ($partnerUser?->id) {
                    $userLocale = $partnerUser->locale ?? app()->getLocale();
                    app(NotificationService::class)->send(
                        type: 'property_correction_requested',
                        title: __('notifications.property_correction_requested_title', [], $userLocale),
                        body: __('notifications.property_correction_requested_body', ['property' => $this->property?->name ?? '', 'reason' => $data['reason']], $userLocale),
                        userIds: $partnerUser->id,
                        data: ['type' => 'property_correction_requested', 'property_id' => $this->property?->id, 'reason' => $data['reason']],
                    );
                }

                Notification::make()
                    ->title(__('admin.correction_request_sent'))
                    ->success()
                    ->send();

                $this->redirect(PropertyVerificationManage::getUrl());
            });
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('admin.reject'))
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->visible(fn (): bool => $this->property !== null && $this->property->verification_status !== PropertyVerificationStatus::Approved)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading('Reject Partner')
            ->modalSubmitActionLabel(__('admin.reject_submission'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalCancelAction(fn (Action $action): Action => $action->link()->color('gray')->extraAttributes(['class' => '!text-gray-700 hover:!text-gray-900 dark:!text-gray-300']))
            ->modalFooterActionsAlignment(Alignment::End)
            ->schema([
                TextEntry::make('warning_banner')
                    ->hiddenLabel()
                    ->state(fn (): HtmlString => new HtmlString(
                        '<div class="flex items-center gap-4 rounded-2xl bg-[#FEF2F2] p-4 dark:bg-red-950/40">'
                        .'<div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl bg-[#DC2626] text-white shadow-sm">'
                        .'<svg class="h-6 w-6 stroke-[2.5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>'
                        .'</div>'
                        .'<p class="text-sm sm:text-base font-bold leading-snug text-gray-900 dark:text-white">This will permanently reject the request and require a new submission.</p>'
                        .'</div>'
                    )),
                TextEntry::make('partner_card')
                    ->hiddenLabel()
                    ->state(function (): HtmlString {
                        $user = $this->property?->partner?->user;
                        $avatar = $user?->avatar;
                        $avatarSrc = $avatar
                            ? (filter_var($avatar, FILTER_VALIDATE_URL) ? $avatar : asset('storage/'.$avatar))
                            : asset('avatars/defaultUser.svg');
                        $name = e($user?->name ?? '');

                        return new HtmlString(
                            '<div class="flex items-center gap-4 rounded-xl border border-gray-200/90 bg-white p-3.5 dark:border-gray-700 dark:bg-gray-800">'
                            .'<img src="'.$avatarSrc.'" alt="'.$name.'" class="h-12 w-12 flex-shrink-0 rounded-lg object-cover" />'
                            .'<span class="text-base font-bold text-gray-900 dark:text-white">'.$name.'</span>'
                            .'</div>'
                        );
                    }),
                Textarea::make('reason')
                    ->label('Reason for Reject')
                    ->placeholder('Enter the reason for permanent rejection...')
                    ->required()
                    ->maxLength(500)
                    ->rows(4)
                    ->helperText('Character Limit: 0 / 500'),
            ])
            ->action(function (array $data): void {
                $this->property?->update([
                    'verification_status' => PropertyVerificationStatus::Rejected,
                    'verification_notes' => $data['reason'],
                ]);

                activity()
                    ->performedOn($this->property)
                    ->causedBy(auth()->user())
                    ->event('rejected')
                    ->withProperties(['reason' => $data['reason']])
                    ->log('Property was rejected');

                $partnerUser = $this->property?->partner?->user;
                if ($partnerUser?->email) {
                    Mail::to($partnerUser->email)->queue(new PropertyRejectedMailable($this->property, $data['reason']));
                }

                if ($partnerUser?->id) {
                    $userLocale = $partnerUser->locale ?? app()->getLocale();
                    app(NotificationService::class)->send(
                        type: 'property_rejected',
                        title: __('notifications.property_rejected_title', [], $userLocale),
                        body: __('notifications.property_rejected_body', ['property' => $this->property?->name ?? '', 'reason' => $data['reason']], $userLocale),
                        userIds: $partnerUser->id,
                        data: ['type' => 'property_rejected', 'property_id' => $this->property?->id, 'reason' => $data['reason']],
                    );
                }

                Notification::make()
                    ->title(__('admin.property_rejected_success'))
                    ->success()
                    ->send();

                $this->redirect(PropertyVerificationManage::getUrl());
            });
    }

    // ── Tab Content (mirrors PropertyView.php's tab helpers) ───────────────

    public function getFacilitiesByCategory(): Collection
    {
        $property = $this->getProperty();
        $selectedIds = $property?->facilities->pluck('id')->toArray() ?? [];

        return FacilityCategory::query()
            ->where('status', 'active')
            ->with(['facilities' => fn ($q) => $q->where('status', 'active')->whereIn('facilities.id', $selectedIds)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($cat) => $cat->facilities->isNotEmpty());
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPropertyRuleAnswers(): array
    {
        $property = $this->getProperty();

        if (! $property) {
            return [];
        }

        $answers = $property->ruleAnswers;

        $rules = PropertyRule::query()
            ->where('status', 'active')
            ->applicableTo($property->country_id, $property->property_type_id)
            ->with(['questions' => fn ($q) => $q->orderBy('sort_order')])
            ->get();

        $grouped = [];

        foreach ($rules as $rule) {
            $ruleData = [
                'name' => $rule->name,
                'icon' => $rule->icon,
                'questions' => [],
            ];

            foreach ($rule->questions as $question) {
                $answer = $answers->firstWhere('property_rule_question_id', $question->id);
                $ruleData['questions'][] = [
                    'question' => $question->question_text,
                    'answer_type' => $question->answer_type->value,
                    'answer' => $this->resolveRuleAnswerLabel($question, $answer?->answer_value),
                ];
            }

            if (count($ruleData['questions']) > 0) {
                $grouped[] = $ruleData;
            }
        }

        return $grouped;
    }

    public function getCancellationPolicy(): ?CancellationPolicy
    {
        $property = $this->getProperty();

        if (! $property) {
            return null;
        }

        return app(CancellationPolicyService::class)->getPolicyForProperty($property);
    }

    public function getNearbyPlaces(): Collection
    {
        $property = $this->getProperty();

        if (! $property || ! $property->ref_city_id) {
            return new Collection;
        }

        $city = City::query()->where('ref_city_id', $property->ref_city_id)->first();

        if (! $city) {
            return new Collection;
        }

        $query = NearbyPlace::query()
            ->where('city_id', $city->id)
            ->with('nearbyPlaceCategory');

        // Distance is specific to this property's exact coordinates, not the
        // city as a whole, so it's computed here rather than stored on the row.
        if ($property->latitude && $property->longitude) {
            $query->select('nearby_places.*')
                ->addSelect(Geo::haversineExpression((float) $property->latitude, (float) $property->longitude))
                ->orderBy('distance_km');
        } else {
            $query->orderBy('name');
        }

        return $query->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGalleryGroups(): array
    {
        $property = $this->getProperty();

        if (! $property) {
            return [];
        }

        return $property->galleryImages()
            ->get()
            ->groupBy('group_name')
            ->map(fn ($images, $name) => [
                'name' => $name,
                'count' => $images->count(),
                'images' => $images->pluck('image_path')->toArray(),
            ])
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDocuments(): array
    {
        $property = $this->getProperty();

        if (! $property) {
            return [];
        }

        return $property->registrationValues
            ->filter(fn ($val) => $val->registrationField && $val->registrationField->field_type->value === 'file_upload')
            ->map(fn ($val) => [
                'name' => $val->registrationField->name,
                'path' => is_array($val->value) ? ($val->value[0] ?? null) : $val->value,
                'uploaded_at' => $val->updated_at,
            ])
            ->values()
            ->toArray();
    }

    public function getDocumentCount(): int
    {
        return count($this->getDocuments());
    }
}
