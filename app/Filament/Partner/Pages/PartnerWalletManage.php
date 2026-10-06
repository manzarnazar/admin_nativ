<?php

namespace App\Filament\Partner\Pages;

use App\Enums\BookingStatus;
use App\Enums\WithdrawalStatus;
use App\Filament\Concerns\RequiresApprovedPartner;
use App\Filament\Partner\Enums\NavigationGroup;
use App\Models\Booking;
use App\Models\Country;
use App\Models\Partner;
use App\Models\PropertyWallet;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\CommissionService;
use App\Services\PropertyWalletService;
use App\Support\PartnerContext;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;

class PartnerWalletManage extends Page
{
    use RequiresApprovedPartner;

    protected static ?string $slug = 'wallet';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.partner.pages.wallet-manage';

    #[Url(as: 'tab')]
    public string $activeTab = 'transactions';

    public function getTitle(): string|Htmlable
    {
        return __('admin.wallet_management');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('admin.wallet_management_subtitle');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroup::WalletManagement;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.wallet');
    }

    public static function getNavigationIcon(): string|Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function switchTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // ── Context Helpers ──────────────────────────────────────────────────────

    private function getPartner(): ?Partner
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->partner;
    }

    private function getCurrentPropertyId(): ?int
    {
        $partner = $this->getPartner();

        if (! $partner) {
            return null;
        }

        $countryId = PartnerContext::currentCountryId($partner);

        return $countryId ? PartnerContext::currentPropertyId($partner, $countryId) : null;
    }

    public function getCurrentWallet(): ?PropertyWallet
    {
        $propertyId = $this->getCurrentPropertyId();

        if (! $propertyId) {
            return null;
        }

        return PropertyWallet::query()
            ->with('property.country')
            ->where('property_id', $propertyId)
            ->first();
    }

    private function getCurrencySymbol(): string
    {
        $partner = $this->getPartner();

        if (! $partner) {
            return '$';
        }

        $countryId = PartnerContext::currentCountryId($partner);

        return ($countryId ? Country::query()->where('id', $countryId)->value('currency_symbol') : null) ?? '$';
    }

    // ── Stats ────────────────────────────────────────────────────────────────

    /**
     * @return array{wallet_balance: string, pending_settlement: string, total_withdrawn: string}
     */
    public function getWalletStats(): array
    {
        $wallet = $this->getCurrentWallet();
        $symbol = $this->getCurrencySymbol();
        $propertyId = $this->getCurrentPropertyId();

        if (! $wallet || ! $propertyId) {
            return [
                'wallet_balance' => $symbol.'0.00',
                'pending_settlement' => $symbol.'0.00',
                'total_withdrawn' => $symbol.'0.00',
            ];
        }

        $walletBalance = (float) $wallet->balance;

        $pendingSettlement = Booking::query()
            ->where('property_id', $propertyId)
            ->whereIn('status', [BookingStatus::Confirmed, BookingStatus::CheckedIn])
            ->whereNull('wallet_credited_at')
            ->get()
            ->sum(fn (Booking $booking): float => app(CommissionService::class)->calculateCheckInPartnerCredit($booking));

        $totalWithdrawn = (float) WithdrawalRequest::query()
            ->where('property_wallet_id', $wallet->id)
            ->where('status', WithdrawalStatus::Approved)
            ->sum('amount');

        return [
            'wallet_balance' => $symbol.number_format($walletBalance, 2),
            'pending_settlement' => $symbol.number_format($pendingSettlement, 2),
            'total_withdrawn' => $symbol.number_format($totalWithdrawn, 2),
        ];
    }

    // ── Header Actions ───────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            $this->withdrawAction(),
        ];
    }

    private function withdrawAction(): Action
    {
        return Action::make('withdraw')
            ->label(__('admin.withdrawal_request'))
            ->color('primary')
            ->visible(fn (): bool => $this->getCurrentWallet()?->getAvailableBalance() > 0)
            ->stickyModalHeader()
            ->stickyModalFooter()
            ->modalHeading(__('admin.request_details'))
            ->modalWidth('lg')
            ->modalFooterActionsAlignment(Alignment::End)
            ->modalSubmitActionLabel(__('admin.send_request'))
            ->schema(function (): array {
                $wallet = $this->getCurrentWallet();
                $symbol = $this->getCurrencySymbol();
                $available = $wallet?->getAvailableBalance() ?? 0;
                $property = $wallet?->property;
                $propertyId = $this->getCurrentPropertyId();
                $bankEditUrl = ($propertyId
                    ? PartnerPropertyCreate::getUrl(['record' => $propertyId])
                    : PartnerPropertyCreate::getUrl()).'#bank-details';

                return [
                    View::make('filament.partner.components.withdrawal-modal-header')
                        ->viewData([
                            'symbol' => $symbol,
                            'available' => $available,
                            'property' => $property,
                            'bankEditUrl' => $bankEditUrl,
                        ]),

                    TextInput::make('amount')
                        ->label(__('admin.enter_amount_for_withdraw'))
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->maxValue($available)
                        ->placeholder('e.g. '.$symbol.'1000'),
                ];
            })
            ->action(function (array $data): void {
                $wallet = $this->getCurrentWallet();
                $partner = $this->getPartner();

                if (! $wallet || ! $partner) {
                    Notification::make()
                        ->title(__('admin.wallet_not_found'))
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    app(PropertyWalletService::class)->requestWithdrawal(
                        wallet: $wallet,
                        partner: $partner,
                        amount: (float) $data['amount'],
                    );

                    Notification::make()
                        ->title(__('admin.withdrawal_request_submitted'))
                        ->success()
                        ->send();

                    $this->switchTab('transactions');
                } catch (\RuntimeException $e) {
                    Notification::make()
                        ->title($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
