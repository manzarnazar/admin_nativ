<x-filament-panels::page>
    @php
    $stats = $this->getReferralStats();
    $settings = $this->getReferralSettings();
    @endphp

    {{-- Stats Cards --}}
    <div class="ref-stats-grid" style="margin-bottom: 24px;">
        {{-- Total Referrals --}}
        <div style="background-color: #EDF4FD; border-radius: 12px; padding: 20px 20px 0 20px; overflow: hidden;">
            <div style="display: flex; align-items: flex-start; gap: 14px; padding-bottom: 16px;">
                <div style="background-color: #4A90D9; border-radius: 10px; padding: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <x-heroicon-o-ticket style="width: 22px; height: 22px; color: white;" />
                </div>
                <div>
                    <p style="font-size: 13px; font-weight: 500; color: #5A6A7A; margin: 0 0 6px 0;">{{ __('admin.stat_total_referrals') }}</p>
                    <p style="font-size: 30px; font-weight: 700; color: #111827; margin: 0; line-height: 1;">{{ $stats['total_referrals'] }}</p>
                </div>
            </div>
            <div style="border-top: 1px solid #D0E3F5; padding: 10px 0;">
                <p style="font-size: 12px; color: #8A9BB0; margin: 0;">{{ __('admin.stat_all_time_referrals') }}</p>
            </div>
        </div>

        {{-- Successful --}}
        <div style="background-color: #EDFBF3; border-radius: 12px; padding: 20px 20px 0 20px; overflow: hidden;">
            <div style="display: flex; align-items: flex-start; gap: 14px; padding-bottom: 16px;">
                <div style="background-color: #2DBB6B; border-radius: 10px; padding: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <x-heroicon-o-check-circle style="width: 22px; height: 22px; color: white;" />
                </div>
                <div>
                    <p style="font-size: 13px; font-weight: 500; color: #5A6A7A; margin: 0 0 6px 0;">{{ __('admin.stat_successful') }}</p>
                    <p style="font-size: 30px; font-weight: 700; color: #111827; margin: 0; line-height: 1;">{{ $stats['successful_referrals'] }}</p>
                </div>
            </div>
            <div style="border-top: 1px solid #C0EDCF; padding: 10px 0;">
                <p style="font-size: 12px; color: #8A9BB0; margin: 0;">{{ __('admin.stat_first_booking_completed') }}</p>
            </div>
        </div>

        {{-- Rewards Credited --}}
        <div style="background-color: #FEF6EC; border-radius: 12px; padding: 20px 20px 0 20px; overflow: hidden;">
            <div style="display: flex; align-items: flex-start; gap: 14px; padding-bottom: 16px;">
                <div style="background-color: #F5A623; border-radius: 10px; padding: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <x-heroicon-o-currency-dollar style="width: 22px; height: 22px; color: white;" />
                </div>
                <div>
                    <p style="font-size: 13px; font-weight: 500; color: #5A6A7A; margin: 0 0 6px 0;">{{ __('admin.stat_rewards_credited') }}</p>
                    <p style="font-size: 30px; font-weight: 700; color: #111827; margin: 0; line-height: 1;">{{ $stats['total_rewards_count'] }}</p>
                </div>
            </div>
            <div style="border-top: 1px solid #F5DDB0; padding: 10px 0;">
                <p style="font-size: 12px; color: #8A9BB0; margin: 0;">{{ __('admin.stat_total_value_disbursed') }}</p>
            </div>
        </div>
    </div>

    {{-- Current Rules Banner --}}
    <div class="ref-rules-banner" style="background-color: #1B1B1B; border-radius: 12px; padding: 16px 20px; margin-bottom: 24px;">
        <div style="display: flex; align-items: flex-start; gap: 14px; flex: 1; min-width: 0;">
            <div style="background-color: #2F2F2F; border-radius: 8px; padding: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; margin-top: 2px;">
                <x-heroicon-o-cog-6-tooth style="width: 18px; height: 18px; color: #AAAAAA;" />
            </div>
            <div style="min-width: 0; flex: 1;">
                <p style="font-size: 14px; font-weight: 700; color: #FFFFFF; margin: 0 0 4px 0;">{{ __('admin.current_rules') }}</p>
                <p style="font-size: 13px; font-weight: 500; color: #CCCCCC; margin: 0; line-height: 1.7; word-break: break-word;">
                    Referrer: {{ $settings['referrer_percentage'] }}% – {{ $settings['referrer_expiry'] }} · Referee: {{ $settings['referee_percentage'] }}% – {{ $settings['referee_expiry'] }} ·
                    @if($settings['enabled'])
                    <span style="background-color: #22C55E; color: white; font-size: 12px; font-weight: 600; padding: 2px 10px; border-radius: 9999px; display: inline-block; margin-top: 2px;">{{ __('admin.referral_status_active') }}</span>
                    @else
                    <span style="background-color: #EF4444; color: white; font-size: 12px; font-weight: 600; padding: 2px 10px; border-radius: 9999px; display: inline-block; margin-top: 2px;">{{ __('admin.referral_status_disabled') }}</span>
                    @endif
                </p>
            </div>
        </div>
        <button
            wire:click="mountAction('editRewardRules')"
            class="ref-edit-btn"
            style="background-color: white; color: #1B1B1B; font-size: 14px; font-weight: 600; padding: 9px 20px; border-radius: 8px; border: none; cursor: pointer; white-space: nowrap;">
            {{ __('admin.edit_reward_rules') }}
        </button>
    </div>

    {{-- Tabs — exact style from help-support page --}}
    <div class="tab-scroll-wrapper" style="margin-bottom: 20px;">
        <div class="tab-pill-row inline-flex gap-1 rounded-xl p-1 dark:bg-gray-800" style="background-color: var(--brand-primary-light);">
            <button
                wire:click="setActiveTab('referee')"
                class="tab-pill-btn inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $this->activeTab === 'referee' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}">
                {{ __('admin.referee_rewards') }}
            </button>
            <button
                wire:click="setActiveTab('referrer')"
                class="tab-pill-btn inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $this->activeTab === 'referrer' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}">
                {{ __('admin.referrer_rewards') }}
            </button>
        </div>
    </div>

    {{-- Table --}}
    {{ $this->table }}

    <x-filament-actions::modals />
</x-filament-panels::page>