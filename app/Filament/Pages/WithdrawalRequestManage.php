<?php

namespace App\Filament\Pages;

use App\Enums\WithdrawalStatus;
use App\Filament\Actions\TableExportAction;
use App\Filament\Concerns\HasPagePermission;
use App\Filament\Contracts\DeclaresTopbarControls;
use App\Filament\Enums\NavigationGroup;
use App\Models\WithdrawalRequest;
use App\Services\PropertyWalletService;
use App\Support\SystemMode;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class WithdrawalRequestManage extends Page implements DeclaresTopbarControls, HasTable
{
    use HasPagePermission {
        canAccess as traitCanAccess;
    }
    use InteractsWithTable;

    protected static ?string $slug = 'withdrawal-requests';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.withdrawal-request-manage';

    public static function getNavigationLabel(): string
    {
        return __('admin.withdrawal_requests');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::resolve(NavigationGroup::Finance);
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
        return __('admin.withdrawal_requests');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('admin.review_and_process_withdrawal_requests');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                WithdrawalRequest::query()
                    ->whereHas('wallet.property', fn (Builder $q) => $q->where('country_id', auth()->user()->current_country_id))
                    ->with(['wallet.property.propertyType', 'partner.user'])
                    ->latest()
            )
            ->columns([
                TextColumn::make('id')
                    ->label(__('admin.payout_id'))
                    ->html()
                    ->state(fn (WithdrawalRequest $record): string => '<span class="font-mono font-medium text-primary-600 dark:text-primary-400">'.str_pad((string) $record->id, 3, '0', STR_PAD_LEFT).'</span>'),

                TextColumn::make('partner.user.name')
                    ->label(__('admin.partner_info'))
                    ->html()
                    ->state(function (WithdrawalRequest $record): Htmlable {
                        $name = e($record->partner?->user?->name ?? '—');
                        $partnerId = $record->partner_id ?? 0;
                        $avatarUrl = $record->partner?->user?->avatar;

                        if ($avatarUrl) {
                            $fullSrc = e(asset('storage/'.$avatarUrl));
                            $avatarHtml = '<img src="'.$fullSrc.'" onclick="event.preventDefault(); event.stopPropagation(); window.showImageModal(this.src)" class="w-8 h-8 rounded-lg object-cover flex-shrink-0 cursor-pointer hover:opacity-80 transition" />';
                        } else {
                            $initial = strtoupper(mb_substr(strip_tags($name), 0, 1));
                            $avatarHtml = '<div class="w-8 h-8 rounded-lg bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center flex-shrink-0 text-xs font-semibold text-primary-700 dark:text-primary-300">'.$initial.'</div>';
                        }

                        $script = '<script>
                            if (!window.showImageModal) {
                                window.showImageModal = function(src) {
                                    let overlay = document.createElement("div");
                                    overlay.className = "fixed inset-0 z-[9999] flex items-center justify-center bg-black/80 backdrop-blur-sm cursor-pointer";
                                    overlay.innerHTML = `<img src="${src}" class="max-h-[90vh] max-w-[90vw] rounded-xl shadow-2xl cursor-default" onclick="event.stopPropagation()"/><button class="absolute top-4 right-4 text-white hover:text-gray-300"><svg class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>`;
                                    overlay.onclick = function() { document.body.removeChild(overlay); };
                                    document.body.appendChild(overlay);
                                }
                            }
                        </script>';

                        return new HtmlString($script.'<div class="flex items-center gap-2">
                            '.$avatarHtml.'
                            <div>
                                <p class="font-medium text-gray-950 dark:text-white">'.$name.'</p>
                                <p class="text-xs text-primary-600 dark:text-primary-400">ID - '.$partnerId.'</p>
                            </div>
                        </div>');
                    })
                    ->searchable(query: fn ($query, string $search) => $query->whereHas(
                        'partner.user',
                        fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")
                    ))
                    ->wrap(),

                TextColumn::make('wallet.property.name')
                    ->label(__('admin.property_info'))
                    ->html()
                    ->state(function (WithdrawalRequest $record): string {
                        $name = e($record->wallet?->property?->name ?? '—');
                        $type = e($record->wallet?->property?->propertyType?->name ?? '');
                        $typeBadge = $type
                            ? '<span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-gray-800 text-white dark:bg-gray-600 mt-0.5">'.$type.'</span>'
                            : '';

                        return '<div><p class="font-medium text-gray-950 dark:text-white">'.$name.'</p>'.$typeBadge.'</div>';
                    })
                    ->wrap(),

                TextColumn::make('amount')
                    ->label(__('admin.amount'))
                    ->html()
                    ->state(fn (WithdrawalRequest $record): string => '<span class="font-semibold text-gray-950 dark:text-white">'.e($record->currency_code).' '.number_format((float) $record->amount, 2).'</span>'),

                TextColumn::make('created_at')
                    ->label(__('admin.withdrawal_date'))
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('processed_at')
                    ->label(__('admin.approval_date'))
                    ->state(fn (WithdrawalRequest $record): string => $record->processed_at
                        ? $record->processed_at->format('d M Y')
                        : '—'),

                TextColumn::make('status')
                    ->label(__('admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (WithdrawalStatus $state): string => $state->label())
                    ->color(fn (WithdrawalStatus $state): string => $state->color()),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.status'))
                    ->options(collect(WithdrawalStatus::cases())->mapWithKeys(
                        fn (WithdrawalStatus $s) => [$s->value => $s->label()]
                    )),
            ])
            ->recordActions([
                // Pending: pencil icon — opens combined details + approve/reject modal
                Action::make('manage')
                    ->iconButton()
                    ->icon('phosphor-pencil-simple-line')
                    ->color('gray')
                    ->visible(fn (WithdrawalRequest $record): bool => $record->isPending())
                    ->modalHeading(__('admin.request_details'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('lg')
                    ->modalFooterActionsAlignment(Alignment::End)
                    ->modalCancelAction(false)
                    ->modalSubmitActionLabel(__('admin.approve_request'))
                    ->modalSubmitAction(fn (Action $action): Action => $action->color('primary'))
                    ->extraModalFooterActions(fn (Action $action): array => [
                        Action::make('rejectRequest')
                            ->label(__('admin.reject_request'))
                            ->color('danger')
                            ->link()
                            ->modalHeading(__('admin.reject_withdrawal'))
                            ->stickyModalHeader()
                            ->stickyModalFooter()
                            ->modalSubmitActionLabel(__('admin.reject'))
                            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
                            ->schema([
                                Textarea::make('admin_notes')
                                    ->label(__('admin.reason_for_rejection'))
                                    ->required()
                                    ->rows(4)
                                    ->maxLength(500)
                                    ->helperText(__('admin.max_500_characters')),
                            ])
                            ->action(function (WithdrawalRequest $record, array $data): void {
                                app(PropertyWalletService::class)->rejectWithdrawal(
                                    request: $record,
                                    admin: auth()->user(),
                                    notes: $data['admin_notes'],
                                );
                                Notification::make()->title(__('admin.withdrawal_rejected_success'))->success()->send();
                            })
                            ->cancelParentActions(),
                    ])
                    ->schema(fn (WithdrawalRequest $record): array => $this->buildRequestDetailsSchema($record))
                    ->action(function (WithdrawalRequest $record): void {
                        app(PropertyWalletService::class)->approveWithdrawal(
                            request: $record,
                            admin: auth()->user(),
                            notes: null,
                        );
                        Notification::make()->title(__('admin.withdrawal_approved_success'))->success()->send();
                    }),

                // Non-pending: eye icon — view only modal
                Action::make('view')
                    ->iconButton()
                    ->icon('phosphor-eye')
                    ->color('gray')
                    ->visible(fn (WithdrawalRequest $record): bool => ! $record->isPending())
                    ->modalHeading(__('admin.request_details'))
                    ->stickyModalHeader()
                    ->stickyModalFooter()
                    ->modalWidth('lg')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('admin.close'))
                    ->schema(fn (WithdrawalRequest $record): array => $this->buildRequestDetailsSchema($record)),
            ])
            ->toolbarActions([
                TableExportAction::make()
                    ->filename('withdrawal-requests')
                    ->exports([
                        'partner.user.name' => __('admin.partner'),
                        'partner.user.email' => __('admin.email'),
                        'wallet.property.name' => __('admin.property'),
                        'currency_code' => __('admin.currency'),
                        'amount' => __('admin.amount'),
                        'status' => [
                            'label' => __('admin.status'),
                            'formatter' => fn (WithdrawalRequest $record): string => $record->status->label(),
                        ],
                        'bank_account_holder' => __('admin.bank_account_holder'),
                        'bank_name' => __('admin.bank_name'),
                        'admin_notes' => __('admin.admin_notes'),
                        'created_at' => __('admin.requested_date'),
                    ])
                    ->toActionGroup(),
            ])
            ->emptyStateHeading(__('admin.no_withdrawal_requests'))
            ->emptyStateDescription('')
            ->defaultSort('created_at', 'desc');
    }

    private function buildRequestDetailsSchema(WithdrawalRequest $record): array
    {
        $partner = $record->partner;
        $user = $partner?->user;
        $property = $record->wallet?->property;

        // Avatar
        $avatarUrl = $user?->avatar;
        if ($avatarUrl) {
            $avatarHtml = '<img src="'.e(asset('storage/'.$avatarUrl)).'" class="w-12 h-12 rounded-xl object-cover flex-shrink-0" alt="" />';
        } else {
            $initial = strtoupper(mb_substr($user?->name ?? '?', 0, 1));
            $avatarHtml = '<div class="w-12 h-12 rounded-xl bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-base font-semibold text-gray-700 dark:text-gray-200 flex-shrink-0">'.$initial.'</div>';
        }

        // Location (city · state_province)
        $city = $partner?->city ?? '';
        $state = $partner?->state_province ?? '';
        $location = trim($city.(($city && $state) ? ' · ' : '').$state);

        // Partner ID formatted as #01
        $partnerId = '#'.str_pad((string) ($partner?->id ?? 0), 2, '0', STR_PAD_LEFT);

        // Property type badge
        $propertyType = e($property?->propertyType?->name ?? '');
        $typeBadge = $propertyType
            ? '<span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium bg-gray-900 text-white dark:bg-gray-700">'.$propertyType.'</span>'
            : '';

        // Status badge colors for dark card
        $statusColors = match ($record->status) {
            WithdrawalStatus::Pending => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
            WithdrawalStatus::Approved => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            WithdrawalStatus::Rejected => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
        };

        $html = '<div class="space-y-3">'

            .'<div class="flex items-center justify-between gap-4 rounded-xl border border-gray-200 p-4 dark:border-gray-700">'
            .'<div class="flex items-center gap-3 min-w-0">'
            .$avatarHtml
            .'<div class="min-w-0">'
            .'<p class="font-semibold text-gray-950 dark:text-white truncate">'.e($user?->name ?? '—').'</p>'
            .($location ? '<p class="text-sm text-gray-500 dark:text-gray-400 truncate">'.e($location).'</p>' : '')
            .'</div>'
            .'</div>'
            .'<div class="shrink-0 text-right">'
            .'<p class="text-xs font-medium text-primary-600 dark:text-primary-400">'.e(__('admin.partner_id')).'</p>'
            .'<p class="text-xl font-bold text-gray-950 dark:text-white">'.e($partnerId).'</p>'
            .'</div>'
            .'</div>'

            // Property card
            .'<div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">'
            .'<p class="font-semibold text-gray-950 dark:text-white">'.e($property?->name ?? '—').'</p>'
            .$typeBadge
            .'</div>'

            // Dark amount card
            .'<div class="rounded-xl bg-gray-900 p-4 dark:bg-black">'
            .'<div class="flex items-center justify-between mb-1">'
            .'<p class="text-sm text-gray-400">'.e(__('admin.requested_amount')).'</p>'
            .'<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium '.$statusColors.'">'.e($record->status->label()).'</span>'
            .'</div>'
            .'<p class="text-3xl font-bold text-white">'.e($record->currency_code).' '.number_format((float) $record->amount, 2).'</p>'
            .'<p class="mt-3 flex items-center gap-1.5 text-sm text-gray-400">'
            .'<svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor">'
            .'<path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />'
            .'</svg>'
            .e(__('admin.requested_on', ['date' => $record->created_at->format('Y-m-d')]))
            .'</p>'
            .'</div>'

            // Payout Destination
            .'<div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">'
            .'<h4 class="mb-4 font-semibold text-gray-950 dark:text-white">'.e(__('admin.payout_destination')).'</h4>'
            .'<div class="space-y-3 text-sm">'
            .'<div class="flex items-center justify-between gap-4">'
            .'<span class="text-gray-500 dark:text-gray-400">'.e(__('admin.bank_name')).'</span>'
            .'<span class="font-medium text-gray-950 dark:text-white">'.e($record->bank_name).'</span>'
            .'</div>'
            .'<div class="flex items-center justify-between gap-4">'
            .'<span class="text-gray-500 dark:text-gray-400">'.e(__('admin.bank_account_number')).'</span>'
            .'<span class="font-mono font-medium text-gray-950 dark:text-white">'.e($record->masked_account_number).'</span>'
            .'</div>'
            .'<div class="flex items-center justify-between gap-4">'
            .'<span class="text-gray-500 dark:text-gray-400">'.e(__('admin.bank_account_holder')).'</span>'
            .'<span class="font-medium text-gray-950 dark:text-white">'.e($record->bank_account_holder).'</span>'
            .'</div>'
            .'<div class="flex items-center justify-between gap-4">'
            .'<span class="text-gray-500 dark:text-gray-400">'.e(__('admin.bank_code')).'</span>'
            .'<span class="font-mono font-medium text-gray-950 dark:text-white">'.e($record->bank_code).'</span>'
            .'</div>'
            .'</div>'
            .'</div>'

            // Admin notes (only for approved/rejected)
            .($record->admin_notes
                ? '<div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">'
                .'<p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">'.e(__('admin.admin_notes')).'</p>'
                .'<p class="text-sm text-gray-950 dark:text-white">'.e($record->admin_notes).'</p>'
                .'</div>'
                : '')

            .'</div>';

        return [
            TextEntry::make('request_details_html')
                ->hiddenLabel()
                ->state(new HtmlString($html)),
        ];
    }
}
