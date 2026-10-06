<?php

namespace App\Filament\Pages;

use App\Enums\PartnerVerificationStatus;
use App\Filament\Concerns\HasPagePermission;
use App\Mail\PartnerApprovedMailable;
use App\Mail\PartnerCorrectionRequestedMailable;
use App\Mail\PartnerRejectedMailable;
use App\Models\Partner;
use App\Services\NotificationService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Url;

class PartnerVerificationDetail extends Page
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithFormActions;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'partner-verification-detail';

    protected string $view = 'filament.pages.partner-verification-detail';

    #[Url]
    public int $partnerId = 0;

    public ?Partner $partner = null;

    public static function canAccess(): bool
    {
        return SystemMode::isMulti() && static::traitCanAccess();
    }

    public function mount(): void
    {
        if (! $this->partnerId) {
            $this->redirect(PartnerVerificationManage::getUrl());

            return;
        }

        $this->partner = Partner::query()
            ->with(['user', 'countries', 'propertyType', 'registrationValues.registrationField'])
            ->findOrFail($this->partnerId);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->partner?->user?->name ?? __('admin.partner_verification');
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * A partner registered via the API-only flow (App\Services\PartnerApi\PartnerAuthService)
     * never collects property type or country — those are only gathered by the
     * partner-panel setup wizard. Approving before that data exists would let an
     * unusable partner through (see App\Livewire\CommissionPartnerOverrideTable,
     * which already defensively filters on the same two conditions elsewhere).
     */
    private function partnerProfileIncomplete(): bool
    {
        return $this->partner !== null
            && ($this->partner->property_type_id === null || $this->partner->countries->isEmpty());
    }

    public function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('admin.approve_account'))
            ->color('success')
            ->icon('heroicon-o-check-circle')
            ->extraAttributes([
                'class' => '!text-white !bg-[#20B364] hover:!bg-[#199653] [&>svg]:!text-white',
            ])
            ->visible(fn (): bool => $this->partner !== null && ! in_array($this->partner->verification_status, [
                PartnerVerificationStatus::Approved,
                PartnerVerificationStatus::Suspended,
            ]))
            ->disabled(fn (): bool => $this->partnerProfileIncomplete())
            ->tooltip(fn (): ?string => $this->partnerProfileIncomplete()
                ? __('admin.partner_profile_incomplete_tooltip')
                : null)
            ->extraModalWindowAttributes(['class' => 'fi-delete-modal-centered'])
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-check-circle')
            ->modalIconColor('success')
            ->modalAlignment(Alignment::Center)
            ->modalHeading(__('admin.approve_account'))
            ->modalDescription(fn (): string => __('admin.approve_account_confirm', ['name' => $this->partner?->user?->name ?? '']))
            ->modalSubmitActionLabel(__('admin.approve_account'))
            ->modalSubmitAction(fn (Action $action): Action => $action->color('success'))
            ->modalCancelActionLabel(__('admin.cancel'))
            ->modalFooterActionsAlignment(Alignment::Center)
            ->action(function (): void {
                $this->partner?->update([
                    'verification_status' => PartnerVerificationStatus::Approved,
                    'verified_at' => now(),
                    'rejection_reason' => null,
                ]);

                activity()
                    ->performedOn($this->partner)
                    ->causedBy(auth()->user())
                    ->event('approved')
                    ->log('Partner account was approved');

                Mail::to($this->partner->user->email)
                    ->queue(new PartnerApprovedMailable($this->partner));

                if ($this->partner?->user_id) {
                    $userLocale = $this->partner->user?->locale ?? app()->getLocale();
                    app(NotificationService::class)->send(
                        type: 'partner_approved',
                        title: __('notifications.partner_approved_title', [], $userLocale),
                        body: __('notifications.partner_approved_body', [], $userLocale),
                        userIds: $this->partner->user_id,
                        data: [
                            'type' => 'partner_approved',
                            'partner_id' => $this->partner->id,
                        ],
                    );
                }

                Notification::make()
                    ->title(__('admin.partner_approved_success'))
                    ->success()
                    ->send();

                $this->redirect(PartnerVerificationManage::getUrl());
            });
    }

    public function requestCorrectionAction(): Action
    {
        return Action::make('requestCorrection')
            ->label(__('admin.request_correction'))
            ->color('gray')
            ->outlined()
            ->icon('heroicon-o-information-circle')
            ->extraAttributes([
                'class' => '!text-gray-700 !border-gray-300 hover:!bg-gray-50 [&>svg]:!text-gray-700 dark:!text-gray-200 dark:!border-gray-600 dark:[&>svg]:!text-gray-200',
            ])
            ->visible(fn (): bool => $this->partner !== null && ! in_array($this->partner->verification_status, [
                PartnerVerificationStatus::Approved,
                PartnerVerificationStatus::Suspended,
            ]))
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
                        $avatar = $this->partner?->user?->avatar;
                        $avatarSrc = $avatar
                            ? (filter_var($avatar, FILTER_VALIDATE_URL) ? $avatar : asset('storage/'.$avatar))
                            : asset('avatars/defaultUser.svg');
                        $name = e($this->partner?->user?->name ?? '');

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
                $this->partner?->update([
                    'verification_status' => PartnerVerificationStatus::CorrectionRequested,
                    'rejection_reason' => $data['reason'],
                ]);

                activity()
                    ->performedOn($this->partner)
                    ->causedBy(auth()->user())
                    ->event('correction_requested')
                    ->withProperties(['reason' => $data['reason']])
                    ->log('Correction requested from partner');

                Mail::to($this->partner->user->email)
                    ->queue(new PartnerCorrectionRequestedMailable($this->partner, $data['reason']));

                if ($this->partner?->user_id) {
                    $userLocale = $this->partner->user?->locale ?? app()->getLocale();
                    app(NotificationService::class)->send(
                        type: 'partner_correction_requested',
                        title: __('notifications.partner_correction_requested_title', [], $userLocale),
                        body: __('notifications.partner_correction_requested_body', ['reason' => $data['reason']], $userLocale),
                        userIds: $this->partner->user_id,
                        data: [
                            'type' => 'partner_correction_requested',
                            'partner_id' => $this->partner->id,
                            'reason' => $data['reason'],
                        ],
                    );
                }

                Notification::make()
                    ->title(__('admin.correction_request_sent'))
                    ->success()
                    ->send();

                $this->redirect(PartnerVerificationManage::getUrl());
            });
    }

    public function rejectAction(): Action
    {
        return Action::make('reject')
            ->label(__('admin.reject_account'))
            ->color('danger')
            ->link()
            ->icon('heroicon-o-x-mark')
            ->extraAttributes([
                'class' => '!text-red-600 hover:!text-red-700 [&>svg]:!text-red-600 hover:[&>svg]:!text-red-700 dark:!text-red-500 dark:[&>svg]:!text-red-500',
            ])
            ->visible(fn (): bool => $this->partner !== null && ! in_array($this->partner->verification_status, [
                PartnerVerificationStatus::Approved,
                PartnerVerificationStatus::Suspended,
            ]))
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
                        $avatar = $this->partner?->user?->avatar;
                        $avatarSrc = $avatar
                            ? (filter_var($avatar, FILTER_VALIDATE_URL) ? $avatar : asset('storage/'.$avatar))
                            : asset('avatars/defaultUser.svg');
                        $name = e($this->partner?->user?->name ?? '');

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
                $this->partner?->update([
                    'verification_status' => PartnerVerificationStatus::Rejected,
                    'rejection_reason' => $data['reason'],
                ]);

                activity()
                    ->performedOn($this->partner)
                    ->causedBy(auth()->user())
                    ->event('rejected')
                    ->withProperties(['reason' => $data['reason']])
                    ->log('Partner account was rejected');

                Mail::to($this->partner->user->email)
                    ->queue(new PartnerRejectedMailable($this->partner, $data['reason']));

                if ($this->partner?->user_id) {
                    $userLocale = $this->partner->user?->locale ?? app()->getLocale();
                    app(NotificationService::class)->send(
                        type: 'partner_rejected',
                        title: __('notifications.partner_rejected_title', [], $userLocale),
                        body: __('notifications.partner_rejected_body', ['reason' => $data['reason']], $userLocale),
                        userIds: $this->partner->user_id,
                        data: [
                            'type' => 'partner_rejected',
                            'partner_id' => $this->partner->id,
                            'reason' => $data['reason'],
                        ],
                    );
                }

                Notification::make()
                    ->title(__('admin.partner_rejected_success'))
                    ->success()
                    ->send();

                $this->redirect(PartnerVerificationManage::getUrl());
            });
    }
}
