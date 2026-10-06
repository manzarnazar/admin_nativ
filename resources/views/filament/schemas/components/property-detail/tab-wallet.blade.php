{{--
    Shared "Wallet" tab body. Expects:
    - $walletStats: array{balance: string, pending_settlement: string, total_withdrawn: string}
    - $walletSubTab: 'transactions'|'pending_settlement'
    - $walletDatePreset, $walletCustomDate: Wallet Transactions date filter, owned by the page
      (not the embedded table) so the Filter row can sit above the stat cards per Figma.
    - $propertyId

    The two sub-views are standalone Livewire TableComponents
    (App\Livewire\PropertyWalletTransactionsTable / PropertyPendingSettlementsTable)
    rather than a second/third table() on this page, for the same reason as
    the Documentations tab: Filament only supports one table() per component.
    The "Exports" action lives on the PAGE (not the child table) for the
    same reason — its closure needs to know which sub-tab is active.
--}}
<div class="rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.filter') }}:</span>

            <select
                wire:model.live="walletDatePreset"
                class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
            >
                <option value="all_time">{{ __('admin.all_time') }}</option>
                <option value="today">{{ __('admin.today') }}</option>
                <option value="this_week">{{ __('admin.this_week') }}</option>
                <option value="this_month">{{ __('admin.this_month') }}</option>
            </select>

            {{--
                Native date inputs ignore the `placeholder` attribute entirely
                and always render the browser's own locale format hint (e.g.
                "dd/mm/yyyy") instead — there's no way to show "Custom Date"
                as placeholder text inside the control itself, so it's a
                separate label instead.
            --}}
            <label class="flex items-center gap-2 rounded-lg border border-gray-300 px-3 text-sm text-gray-700 dark:border-gray-600 dark:text-gray-200">
                <span class="text-gray-500 dark:text-gray-400">{{ __('admin.custom_date') }}:</span>
                <input
                    type="date"
                    wire:model.live="walletCustomDate"
                    class="border-0 bg-transparent p-0 py-2 text-sm text-gray-700 focus:ring-0 dark:text-gray-200"
                />
            </label>
        </div>

        <x-filament-actions::group
            :actions="$this->getWalletExportActions()"
            :label="__('admin.exports')"
            icon="heroicon-o-arrow-down-tray"
            color="dark"
            :button="true"
        />
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        <div class="rounded-xl bg-gray-900 p-5 dark:bg-black">
            <p class="text-xs font-medium text-gray-300">{{ __('admin.wallet_balance') }}</p>
            <p class="mt-2 text-2xl font-bold text-white">{{ $walletStats['balance'] }}</p>
        </div>
        <div class="rounded-xl border border-orange-200 bg-orange-50 p-5 dark:border-orange-800 dark:bg-orange-950/20">
            <p class="text-xs font-medium text-orange-700 dark:text-orange-400">{{ __('admin.pending_settlement') }}</p>
            <p class="mt-2 text-2xl font-bold text-orange-700 dark:text-orange-300">{{ $walletStats['pending_settlement'] }}</p>
        </div>
        <div class="rounded-xl border border-green-200 bg-green-50 p-5 dark:border-green-800 dark:bg-green-950/20">
            <p class="text-xs font-medium text-green-700 dark:text-green-400">{{ __('admin.total_withdrawn') }}</p>
            <p class="mt-2 text-2xl font-bold text-green-700 dark:text-green-300">{{ $walletStats['total_withdrawn'] }}</p>
        </div>
    </div>

    <div class="flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">
            {{ $walletSubTab === 'transactions' ? __('admin.wallet_transactions') : __('admin.pending_settlement') }}
        </h3>

        <div class="inline-flex gap-1 rounded-xl bg-gray-100 p-1 dark:bg-gray-800">
            <button
                type="button"
                wire:click="switchWalletTab('transactions')"
                @class([
                    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition',
                    'bg-primary-600 text-white shadow-sm' => $walletSubTab === 'transactions',
                    'text-gray-600 dark:text-gray-300' => $walletSubTab !== 'transactions',
                ])
            >
                {{ __('admin.wallet_transactions') }}
            </button>

            <button
                type="button"
                wire:click="switchWalletTab('pending_settlement')"
                @class([
                    'inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition',
                    'bg-primary-600 text-white shadow-sm' => $walletSubTab === 'pending_settlement',
                    'text-gray-600 dark:text-gray-300' => $walletSubTab !== 'pending_settlement',
                ])
            >
                {{ __('admin.pending_settlement') }}
            </button>
        </div>
    </div>

    @if ($walletSubTab === 'transactions')
        @livewire('property-wallet-transactions-table', [
            'propertyId' => $propertyId,
            'datePreset' => $walletDatePreset,
            'customDate' => $walletCustomDate,
        ], key('property-wallet-transactions-'.$propertyId.'-'.$walletDatePreset.'-'.$walletCustomDate))
    @else
        @livewire('property-pending-settlements-table', ['propertyId' => $propertyId], key('property-pending-settlements-'.$propertyId))
    @endif
</div>
</div>
