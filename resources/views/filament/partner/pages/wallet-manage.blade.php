<x-filament-panels::page>
    @php
        $stats = $this->getWalletStats();
        $activeStyle = 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);';
        $inactiveStyle = 'color: #4b5563;';
    @endphp

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        {{-- Wallet Balance (dark card) --}}
        <div class="rounded-xl p-5 shadow-sm" style="background-color: #1e1e2e;">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl" style="background-color: rgba(255,255,255,0.12);">
                    <x-heroicon-o-wallet class="h-7 w-7 text-white" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-400">
                        {{ __('admin.wallet_balance') }}
                    </p>
                    <p class="text-2xl font-bold text-white">
                        {{ $stats['wallet_balance'] }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Pending Settlement --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-warning-100 dark:bg-warning-900">
                    <x-heroicon-o-clock class="h-7 w-7 text-warning-500 dark:text-warning-400" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ __('admin.pending_settlement') }}
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $stats['pending_settlement'] }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Total Withdrawn --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center gap-4">
                <div class="flex h-14 w-14 flex-shrink-0 items-center justify-center rounded-2xl bg-success-100 dark:bg-success-900">
                    <x-heroicon-o-banknotes class="h-7 w-7 text-success-600 dark:text-success-400" />
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ __('admin.total_withdrawn') }}
                    </p>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $stats['total_withdrawn'] }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- Section Heading + Tab Switcher --}}
    <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">
            {{ $activeTab === 'transactions' ? __('admin.wallet_transactions') : __('admin.pending_settlement') }}
        </h2>

        <div class="inline-flex gap-1 rounded-xl p-1 dark:bg-gray-800" style="background-color: var(--brand-primary-light);">
            <button
                wire:click="switchTab('transactions')"
                class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $activeTab === 'transactions' ? $activeStyle : $inactiveStyle }}"
            >
                {{ __('admin.wallet_transactions') }}
            </button>

            <button
                wire:click="switchTab('settlements')"
                class="inline-flex items-center rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $activeTab === 'settlements' ? $activeStyle : $inactiveStyle }}"
            >
                {{ __('admin.pending_settlement') }}
            </button>
        </div>
    </div>

    {{-- Tab Content --}}
    @if ($activeTab === 'transactions')
        @livewire('partner-wallet-transactions-table', key('partner-wallet-transactions'))
    @else
        @livewire('partner-pending-settlements-table', key('partner-pending-settlements'))
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
