@php
    $policy = $this->getCancellationPolicy();
    $showCancellationRuleForm = $this->showCancellationRuleForm;
    $editingCancellationRuleId = $this->editingCancellationRuleId;
    $cancellationRulesList = $this->cancellationRulesList;
    $cancellationRuleData = $this->cancellationRuleData;
    $cancellationPolicySource = $this->cancellationPolicySource;
    $adminDefaultPolicy = $this->getAdminDefaultCancellationPolicy();
    $adminDefaultRules = $adminDefaultPolicy?->rules ?? collect();
    $adminPolicyConfigured = $adminDefaultRules->count() > 0;
@endphp

<div class="space-y-6">
    {{-- Policy Source --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        {{-- Admin Default --}}
        @if ($adminPolicyConfigured)
            <div wire:click="$set('cancellationPolicySource', 'admin_default')"
                class="cursor-pointer rounded-xl border p-5 transition-colors {{ $cancellationPolicySource === 'admin_default' ? 'border-primary-500 ring-2 ring-primary-100 dark:ring-primary-900' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600' }}">
                <div class="flex items-center gap-3">
                    <span @class([
                        'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                        'bg-primary-600 text-white' => $cancellationPolicySource === 'admin_default',
                        'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $cancellationPolicySource !== 'admin_default',
                    ])>
                        <x-heroicon-o-shield-check class="h-5 w-5" />
                    </span>
                    <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.admin_default_policy') }}</span>
                </div>
                <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    {{ __('admin.admin_default_policy_description') }}
                </p>
            </div>
        @else
            <div class="cursor-not-allowed rounded-xl border border-gray-200 bg-gray-50 p-5 opacity-60 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500">
                        <x-heroicon-o-lock-closed class="h-5 w-5" />
                    </span>
                    <span class="text-sm font-semibold text-gray-400 dark:text-gray-500">{{ __('admin.admin_default_policy') }}</span>
                </div>
                <p class="mt-3 text-sm leading-relaxed text-gray-400 dark:text-gray-500">
                    {{ __('admin.admin_default_policy_description') }}
                </p>
                <p class="mt-3 flex items-center gap-1.5 text-xs font-medium text-amber-600 dark:text-amber-400">
                    <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5 shrink-0" />
                    {{ __('admin.admin_default_policy_not_configured') }}
                </p>
            </div>
        @endif

        {{-- Custom Policy --}}
        <div wire:click="$set('cancellationPolicySource', 'custom')"
            class="cursor-pointer rounded-xl border p-5 transition-colors {{ $cancellationPolicySource === 'custom' ? 'border-primary-500 ring-2 ring-primary-100 dark:ring-primary-900' : 'border-gray-200 hover:border-gray-300 dark:border-gray-700 dark:hover:border-gray-600' }}">
            <div class="flex items-center gap-3">
                <span @class([
                    'flex h-9 w-9 shrink-0 items-center justify-center rounded-lg',
                    'bg-primary-600 text-white' => $cancellationPolicySource === 'custom',
                    'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' => $cancellationPolicySource !== 'custom',
                ])>
                    <x-heroicon-o-computer-desktop class="h-5 w-5" />
                </span>
                <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.custom_policy') }}</span>
            </div>
            <p class="mt-3 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                {{ __('admin.custom_policy_description') }}
            </p>
        </div>
    </div>

    @if ($cancellationPolicySource === 'admin_default')
        <div>
            <h4 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.admin_default_policy') }}</h4>

            @if ($adminDefaultPolicy?->cancellation_cutoff_time)
                <div class="mb-4 flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-4 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                    <x-heroicon-o-clock class="h-4 w-4 shrink-0 text-gray-500" />
                    <span class="text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-semibold">{{ __('admin.cancellation_cutoff_time') }}:</span>
                        {{ substr($adminDefaultPolicy->cancellation_cutoff_time, 0, 5) }}
                    </span>
                </div>
            @endif

            @if ($adminDefaultRules->count() > 0)
                <div class="space-y-4">
                    @foreach ($adminDefaultRules as $index => $rule)
                        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                            <div class="mb-4 flex items-center gap-3">
                                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-600 text-xs font-bold text-white">
                                    {{ $index + 1 }}
                                </span>
                                <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ __('admin.rule_phase', ['number' => $index + 1]) }}
                                </h4>
                            </div>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ __('admin.days_before_checkin') }}
                                    </label>
                                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-800">
                                        <x-heroicon-o-calendar class="h-4 w-4 text-gray-400" />
                                        <span class="text-sm text-gray-900 dark:text-white">{{ $rule->days_before_checkin }}</span>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ __('admin.is_this_refundable') }}
                                    </label>
                                    <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-800">
                                        <span class="text-sm text-gray-900 dark:text-white">
                                            {{ $rule->refund_percentage > 0 ? __('admin.yes_refundable') : __('admin.non_refundable') }}
                                        </span>
                                    </div>
                                </div>

                                <div>
                                    <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ __('admin.refund_percentage') }}
                                    </label>
                                    <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-800">
                                        <span class="text-sm font-medium text-gray-400">%</span>
                                        <span class="text-sm text-gray-900 dark:text-white">{{ $rule->refund_percentage }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-200 px-6 py-12 text-center dark:border-gray-700">
                    <x-heroicon-o-document-text class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.no_cancellation_policy') }}</p>
                    <p class="max-w-sm text-xs text-gray-400 dark:text-gray-500">{{ __('admin.no_cancellation_policy_description') }}</p>
                </div>
            @endif
        </div>
    @endif

    @if ($cancellationPolicySource === 'custom')
    {{-- Cutoff Time --}}
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="mb-3 flex items-center gap-2">
            <x-heroicon-o-clock class="h-4 w-4 text-gray-500" />
            <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.cancellation_cutoff_time') }}</h4>
        </div>

        {{ $this->cancellationCutoffForm }}

        <div class="mt-3">
            <x-filament::button wire:click="saveCancellationCutoffTime" size="sm">
                {{ __('admin.save_changes') }}
            </x-filament::button>
        </div>
    </div>

    {{-- Rule Phases --}}
    <div class="flex items-center justify-between">
        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.cancellation_policy') }}</h4>
        @if (! $showCancellationRuleForm)
            <x-filament::button wire:click="addNewCancellationRule" icon="heroicon-o-plus" size="sm">
                {{ __('admin.add_rule') }}
            </x-filament::button>
        @endif
    </div>

    @foreach ($cancellationRulesList as $index => $ruleItem)
        @if ($showCancellationRuleForm && $editingCancellationRuleId === $ruleItem['id'])
            {{-- INLINE EDIT --}}
            <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-600 text-xs font-bold text-white">
                            {{ $index + 1 }}
                        </span>
                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.rule_phase', ['number' => $index + 1]) }}</h4>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-filament::icon-button
                            icon="heroicon-o-trash"
                            color="gray"
                            wire:click="cancelCancellationRule"
                            :tooltip="__('admin.discard')"
                        />
                        <x-filament::button wire:click="saveCancellationRule" size="sm">
                            {{ __('admin.save_rule') }}
                        </x-filament::button>
                    </div>
                </div>

                {{ $this->cancellationRuleForm }}

                <div class="mt-4 flex items-center gap-2 rounded-lg bg-primary-50 px-4 py-2.5 dark:bg-primary-950">
                    <x-heroicon-o-computer-desktop class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" />
                    <span class="text-sm text-primary-700 dark:text-primary-300">
                        <span class="font-semibold">{{ __('admin.preview') }}:</span>
                        @if (($cancellationRuleData['days_before'] ?? null) !== null && $cancellationRuleData['days_before'] !== '')
                            @if (($cancellationRuleData['is_refundable'] ?? '') === 'refundable' && ($cancellationRuleData['refund_percent'] ?? 0) > 0)
                                {{ $cancellationRuleData['refund_percent'] }}% {{ __('admin.refund_if_cancelled') }} {{ $cancellationRuleData['days_before'] }} {{ __('admin.days_before_checkin') }}
                            @else
                                {{ __('admin.non_refundable') }} {{ __('admin.if_cancelled') }} {{ $cancellationRuleData['days_before'] }} {{ __('admin.days_before_checkin') }}
                            @endif
                        @else
                            --
                        @endif
                    </span>
                </div>
            </div>
        @else
            {{-- DISPLAY CARD --}}
            <div class="flex flex-wrap items-center gap-3 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex min-w-0 flex-1 items-center gap-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-xs font-bold text-white">
                        {{ $index + 1 }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.days_before_checkin') }}</p>
                        <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $ruleItem['days_before_checkin'] }}</p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-4">
                    <div class="text-right">
                        <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $ruleItem['refund_percentage'] > 0 ? __('admin.yes_refundable') : __('admin.non_refundable') }}
                        </p>
                        @if ($ruleItem['refund_percentage'] > 0)
                            <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $ruleItem['refund_percentage'] }}%</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2 border-l border-gray-200 pl-4 dark:border-gray-700">
                        <x-filament::icon-button
                            icon="heroicon-o-pencil"
                            color="gray"
                            wire:click="editCancellationRule({{ $ruleItem['id'] }})"
                            :tooltip="__('admin.edit')"
                        />
                        {{ ($this->deleteCancellationRuleAction)(['ruleId' => $ruleItem['id']]) }}
                    </div>
                </div>
            </div>
        @endif
    @endforeach

    {{-- NEW RULE FORM --}}
    @if ($showCancellationRuleForm && $editingCancellationRuleId === null)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
                <div class="flex items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-600 text-xs font-bold text-white">
                        {{ count($cancellationRulesList) + 1 }}
                    </span>
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.rule_phase', ['number' => count($cancellationRulesList) + 1]) }}</h4>
                </div>
                <div class="flex items-center gap-2">
                    <x-filament::icon-button
                        icon="heroicon-o-trash"
                        color="gray"
                        wire:click="cancelCancellationRule"
                        :tooltip="__('admin.discard')"
                    />
                    <x-filament::button wire:click="saveCancellationRule" size="sm">
                        {{ __('admin.save_rule') }}
                    </x-filament::button>
                </div>
            </div>

            {{ $this->cancellationRuleForm }}

            <div class="mt-4 flex items-center gap-2 rounded-lg bg-primary-50 px-4 py-2.5 dark:bg-primary-950">
                <x-heroicon-o-computer-desktop class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" />
                <span class="text-sm text-primary-700 dark:text-primary-300">
                    <span class="font-semibold">{{ __('admin.preview') }}:</span>
                    @if (($cancellationRuleData['days_before'] ?? null) !== null && $cancellationRuleData['days_before'] !== '')
                        @if (($cancellationRuleData['is_refundable'] ?? '') === 'refundable' && ($cancellationRuleData['refund_percent'] ?? 0) > 0)
                            {{ $cancellationRuleData['refund_percent'] }}% {{ __('admin.refund_if_cancelled') }} {{ $cancellationRuleData['days_before'] }} {{ __('admin.days_before_checkin') }}
                        @else
                            {{ __('admin.non_refundable') }} {{ __('admin.if_cancelled') }} {{ $cancellationRuleData['days_before'] }} {{ __('admin.days_before_checkin') }}
                        @endif
                    @else
                        --
                    @endif
                </span>
            </div>
        </div>
    @endif

    @if (empty($cancellationRulesList) && ! $showCancellationRuleForm)
        <div class="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-200 px-6 py-12 text-center dark:border-gray-700">
            <x-heroicon-o-document-text class="h-8 w-8 text-gray-400 dark:text-gray-500" />
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.no_cancellation_policy') }}</p>
            <p class="max-w-sm text-xs text-gray-400 dark:text-gray-500">{{ __('admin.no_cancellation_policy_description') }}</p>
        </div>
    @endif
    @endif
</div>
