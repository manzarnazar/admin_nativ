<x-filament-panels::page>
    @php
        $customer = $this->getCustomer();
        $stats = $this->getCustomerStats();
        $currency = \App\Models\Country::where('id', auth()->user()->current_country_id)->value('currency_symbol') ?? '$';
    @endphp

    {{-- Back Link --}}
    <div class="-mt-4 mb-3">
        <a href="{{ \App\Filament\Pages\AllCustomersManage::getUrl() }}" wire:navigate class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back_to_all_customers') }}
        </a>
    </div>

    {{-- Top Section: Customer Info + Overview --}}
    <div class="customer-top-grid">
        {{-- Left: Customer Information --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden flex flex-col">
            <div class="p-4 border-b border-gray-200 flex-shrink-0">
                <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin: 0;">{{ __('admin.customer_information') }}</h3>
            </div>
            <div class="p-4 flex-1 flex flex-col justify-between">
                <div>
                    <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                        <div style="width: 56px; height: 56px; border-radius: 50%; overflow: hidden; background: #e5e7eb; flex-shrink: 0;">
                            @if ($customer->avatar && str_starts_with($customer->avatar, 'avatars/'))
                                <img src="{{ asset('storage/' . $customer->avatar) }}" style="width: 100%; height: 100%; object-fit: cover;" />
                            @else
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                                    <x-heroicon-o-user style="width: 28px; height: 28px; color: #9ca3af;" />
                                </div>
                            @endif
                        </div>
                        <div style="flex: 1;">
                            <p style="font-size: 16px; font-weight: 700; color: #111827; margin: 0;">{{ $customer->name }}</p>
                            <p style="font-size: 12px; color: #2563eb; font-weight: 600; margin: 2px 0 0 0;">ID-{{ str_pad((string) $customer->id, 3, '0', STR_PAD_LEFT) }}</p>
                        </div>
                        <span style="
                            font-size: 11px; font-weight: 500; padding: 4px 12px; border-radius: 20px;
                            {{ $customer->status === \App\Enums\UserStatus::Active ? 'background-color: #dcfce7; color: #15803d;' : 'background-color: #fee2e2; color: #dc2626;' }}
                        ">{{ $customer->status->label() }}</span>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 14px; font-size: 13px;">
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <x-heroicon-o-envelope style="width: 16px; height: 16px; color: #6b7280; flex-shrink: 0; margin-top: 2px;" />
                            <div>
                                <p style="color: #6b7280; margin: 0; font-size: 11px;">{{ __('admin.email') }}</p>
                                <p style="color: #111827; font-weight: 500; margin: 2px 0 0 0;">{{ \App\Support\DemoMode::maskEmail($customer->email) ?? '-' }}</p>
                            </div>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <x-heroicon-o-phone style="width: 16px; height: 16px; color: #6b7280; flex-shrink: 0; margin-top: 2px;" />
                            <div>
                                <p style="color: #6b7280; margin: 0; font-size: 11px;">{{ __('admin.phone') }}</p>
                                <p style="color: #111827; font-weight: 500; margin: 2px 0 0 0;">{{ $customer->phone ? (($customer->dial_code ? $customer->dial_code . ' ' : '') . \App\Support\DemoMode::maskPhone($customer->phone)) : '-' }}</p>
                            </div>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <x-heroicon-o-clock style="width: 16px; height: 16px; color: #6b7280; flex-shrink: 0; margin-top: 2px;" />
                            <div>
                                <p style="color: #6b7280; margin: 0; font-size: 11px;">{{ __('admin.last_active') }}</p>
                                <p style="color: #111827; font-weight: 500; margin: 2px 0 0 0;">{{ $customer->last_active_at ? $customer->last_active_at->diffForHumans() : '-' }}</p>
                            </div>
                        </div>
                        <div style="display: flex; align-items: flex-start; gap: 8px;">
                            <x-heroicon-o-calendar style="width: 16px; height: 16px; color: #6b7280; flex-shrink: 0; margin-top: 2px;" />
                            <div>
                                <p style="color: #6b7280; margin: 0; font-size: 11px;">{{ __('admin.joined_date') }}</p>
                                <p style="color: #111827; font-weight: 500; margin: 2px 0 0 0;">{{ $customer->created_at->format('d M Y') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #f3f4f6; display: flex; justify-content: center;" class="flex-shrink-0">
                    {{ $this->suspendAccountAction }}
                </div>
            </div>
        </div>

        {{-- Right: Customer Overview --}}
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="p-4 border-b border-gray-200">
                <h3 style="font-size: 18px; font-weight: 700; color: #111827; margin: 0;">{{ __('admin.customer_overview') }}</h3>
            </div>
            <div class="p-4">

            {{-- Total Spent (dark card) --}}
            <div style="background-color: #1a1a1a; border: 1px solid #ededed; border-radius: 16px; padding: 24px; color: white; display: flex; align-items: center; gap: 28px; margin-bottom: 24px;">
                <div style="background-color: #333; border-radius: 8px; padding: 12px;">
                    <x-heroicon-o-currency-dollar style="width: 24px; height: 24px;" />
                </div>
                <div>
                    <p style="font-size: 16px; font-weight: 400; color: #d9d9d9; margin: 0;">{{ __('admin.total_spent') }}</p>
                    <p style="font-size: 24px; font-weight: 600; margin: 0; line-height: 32px;">{{ $currency }}{{ number_format((float) $stats['total_spent'], 2) }}</p>
                </div>
            </div>

            {{-- Stats Grid (2x2) --}}
            <div class="customer-stats-grid">
                {{-- Total Bookings (green) - row 1 col 1 --}}
                <div style="background-color: #e5faef; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
                    <div style="background-color: #20b364; border-radius: 8px; padding: 12px;">
                        <x-heroicon-o-calendar-days style="width: 24px; height: 24px; color: white;" />
                    </div>
                    <div>
                        <p style="font-size: 14px; font-weight: 500; color: #555; margin: 0;">{{ __('admin.total_bookings') }}</p>
                        <p style="font-size: 20px; font-weight: 700; color: #0d0e0d; margin: 0; line-height: 28px;">{{ $stats['total_bookings'] }}</p>
                    </div>
                </div>

                {{-- Total Refund Amount (orange) - row 1 col 2 --}}
                <div style="background-color: #fef5e6; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
                    <div style="background-color: #f79e1b; border-radius: 8px; padding: 12px;">
                        <x-heroicon-o-arrow-uturn-left style="width: 24px; height: 24px; color: white;" />
                    </div>
                    <div>
                        <p style="font-size: 14px; font-weight: 500; color: #555; margin: 0;">{{ __('admin.total_refund_amount') }}</p>
                        <p style="font-size: 20px; font-weight: 700; color: #0d0e0d; margin: 0; line-height: 28px;">{{ $currency }}{{ number_format((float) $stats['total_refunded'], 2) }}</p>
                    </div>
                </div>

                {{-- Total Cancelled Bookings (red) - row 2 col 1 --}}
                <div style="background-color: #fbeaea; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
                    <div style="background-color: #d63031; border-radius: 8px; padding: 12px;">
                        <x-heroicon-o-x-circle style="width: 24px; height: 24px; color: white;" />
                    </div>
                    <div>
                        <p style="font-size: 14px; font-weight: 500; color: #555; margin: 0;">{{ __('admin.total_cancelled_bookings') }}</p>
                        <p style="font-size: 20px; font-weight: 700; color: #0d0e0d; margin: 0; line-height: 28px;">{{ $stats['total_cancelled'] }}</p>
                    </div>
                </div>

                {{-- Joined Platform (blue) - row 2 col 2 --}}
                <div style="background-color: #e7f4fe; border-radius: 16px; padding: 16px; display: flex; align-items: center; gap: 16px;">
                    <div style="background-color: #2196f3; border-radius: 8px; padding: 12px;">
                        <x-heroicon-o-device-phone-mobile style="width: 24px; height: 24px; color: white;" />
                    </div>
                    <div>
                        <p style="font-size: 14px; font-weight: 500; color: #555; margin: 0;">{{ __('admin.joined_platform') }}</p>
                        <p style="font-size: 20px; font-weight: 700; color: #0d0e0d; margin: 0; line-height: 28px;">{{ $stats['joined_platform'] }}</p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl bg-[#f7f7f7] p-4 dark:bg-gray-800/40" style="margin-top: 24px;">
                <p style="font-size: 13px; color: #555; margin: 0; line-height: 18px;" class="dark:text-gray-400">
                    <strong style="color: #111827; font-weight: 600;" class="dark:text-white">{{ __('admin.data_scope') }}:</strong> {{ __('admin.data_scope_description') }}
                </p>
            </div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex gap-6">
            <button
                wire:click="switchTab('bookings')"
                @class([
                    'inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition',
                    'border-primary-600 text-primary-600' => $this->activeTab === 'bookings',
                    'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => $this->activeTab !== 'bookings',
                ])
            >
                <x-heroicon-o-calendar-days class="h-4 w-4" />
                {{ __('admin.booking_history') }}
            </button>
            <button
                wire:click="switchTab('insights')"
                @class([
                    'inline-flex items-center gap-2 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition',
                    'border-primary-600 text-primary-600' => $this->activeTab === 'insights',
                    'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700' => $this->activeTab !== 'insights',
                ])
            >
                <x-heroicon-o-chart-bar class="h-4 w-4" />
                {{ __('admin.activity_and_insights') }}
            </button>
        </nav>
    </div>

    {{-- Tab Content --}}
    @if ($this->activeTab === 'bookings')
        {{ $this->table }}
    @else
        @include('filament.pages.customer-view.tab-insights', ['customer' => $customer])
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
