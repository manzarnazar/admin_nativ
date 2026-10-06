@php
    $showCancellationRuleForm = $this->showCancellationRuleForm;
    $editingCancellationRuleId = $this->editingCancellationRuleId;
    $cancellationRulesList = $this->cancellationRulesList;
    $cancellationRuleData = $this->cancellationRuleData;
@endphp

<div class="grid grid-cols-1 gap-6">

    {{-- CUTOFF TIME CARD --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center gap-3 border-b border-gray-200 bg-blue-50/50 px-6 py-4 dark:border-gray-700 dark:bg-blue-900/10">
            <div class="rounded-lg bg-blue-100 p-2 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400">
                <x-heroicon-o-clock class="h-5 w-5" />
            </div>
            <div>
                <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('admin.global_settings') }}</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.set_the_universal_cancellation_cutoff_time_for_this_country') }}</p>
            </div>
        </div>

        <div class="p-6">
            {{ $this->cancellationCutoffForm }}

            <div class="mt-4">
                <x-filament::button wire:click="saveCancellationCutoffTime" size="sm">
                    {{ __('admin.save_changes') }}
                </x-filament::button>
            </div>
        </div>
    </div>

    {{-- CANCELLATION RULES CARD --}}
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-800/50">
            <div class="flex items-center gap-3">
                <div class="rounded-lg bg-blue-100 p-2 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400">
                    <x-heroicon-o-no-symbol class="h-5 w-5" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('admin.cancellation_policy') }}</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.rules_regarding_booking_cancellations_refunds_and_noshows') }}</p>
                </div>
            </div>

            @if (! $showCancellationRuleForm)
                <x-filament::button wire:click="addNewCancellationRule" icon="heroicon-o-plus" size="sm">
                    {{ __('admin.add_rule') }}
                </x-filament::button>
            @endif
        </div>

        <div class="space-y-4 bg-gray-50/50 p-6 dark:bg-gray-900/50">

            {{-- Listed Rules --}}
            @foreach ($cancellationRulesList as $index => $ruleItem)
                @if ($showCancellationRuleForm && $editingCancellationRuleId === $ruleItem['id'])
                    {{-- INLINE EDIT --}}
                    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
                            <div class="flex items-center gap-3">
                                <div class="flex h-8 w-8 items-center justify-center rounded-md bg-gray-900 text-sm font-bold text-white dark:bg-gray-700">
                                    {{ $index + 1 }}
                                </div>
                                <span class="text-sm font-bold text-gray-900 dark:text-white">{{ __('admin.rule') }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::icon-button
                                    icon="heroicon-o-x-mark"
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

                        @if (($cancellationRuleData['days_before'] ?? null) !== null && $cancellationRuleData['days_before'] !== '')
                            <div class="mt-4 flex items-center gap-3 rounded-lg bg-blue-50 px-4 py-3 dark:bg-blue-900/20">
                                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                                    <x-heroicon-o-document-text class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                </div>
                                <p class="text-sm text-blue-800 dark:text-blue-300">
                                    <span class="font-semibold">{{ __('admin.preview') }}:</span>
                                    @if (($cancellationRuleData['is_refundable'] ?? '') === 'refundable' && ($cancellationRuleData['refund_percent'] ?? 0) > 0)
                                        {{ $cancellationRuleData['refund_percent'] }}% {{ __('admin.refund_if_cancelled') }} {{ $cancellationRuleData['days_before'] }} {{ __('admin.days_before_checkin') }}
                                    @else
                                        {{ __('admin.non_refundable') }} {{ __('admin.if_cancelled') }} {{ $cancellationRuleData['days_before'] }} {{ __('admin.days_before_checkin') }}
                                    @endif
                                </p>
                            </div>
                        @endif
                    </div>
                @else
                    {{-- DISPLAY CARD --}}
                    <div class="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-center gap-4">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-gray-900 text-sm font-bold text-white dark:bg-gray-700">
                                {{ $index + 1 }}
                            </div>
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ __('admin.cancellation_period') }}</p>
                                <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $ruleItem['days_before_checkin'] }} {{ __('admin.days_before_checkin') }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-6">
                            <div class="text-right">
                                <p class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    {{ $ruleItem['refund_percentage'] > 0 ? __('admin.yes_refundable') : __('admin.non_refundable') }}
                                </p>
                                @if ($ruleItem['refund_percentage'] > 0)
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $ruleItem['refund_percentage'] }}%</p>
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
                <div class="mt-4 rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="mb-4 flex items-center justify-between border-b border-gray-200 pb-3 dark:border-gray-700">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-gray-900 text-sm font-bold text-white dark:bg-gray-700">
                                {{ count($cancellationRulesList) + 1 }}
                            </div>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">{{ __('admin.rule') }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-filament::icon-button
                                icon="heroicon-o-x-mark"
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

                    @if (($cancellationRuleData['days_before'] ?? null) !== null && $cancellationRuleData['days_before'] !== '')
                        <div class="mt-4 flex items-center gap-3 rounded-lg bg-blue-50 px-4 py-3 dark:bg-blue-900/20">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                                <x-heroicon-o-document-text class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                            </div>
                            <p class="text-sm text-blue-800 dark:text-blue-300">
                                <span class="font-semibold">{{ __('admin.preview') }}:</span>
                                @if (($cancellationRuleData['is_refundable'] ?? '') === 'refundable' && ($cancellationRuleData['refund_percent'] ?? 0) > 0)
                                    {{ $cancellationRuleData['refund_percent'] }}% {{ __('admin.refund_if_cancelled') }} {{ $cancellationRuleData['days_before'] }} {{ __('admin.days_before_checkin') }}
                                @else
                                    {{ __('admin.non_refundable') }} {{ __('admin.if_cancelled') }} {{ $cancellationRuleData['days_before'] }} {{ __('admin.days_before_checkin') }}
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Empty State --}}
            @if (count($cancellationRulesList) === 0 && ! $showCancellationRuleForm)
                <div class="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-200 px-6 py-12 text-center dark:border-gray-700">
                    <x-heroicon-o-document-text class="h-8 w-8 text-gray-400 dark:text-gray-500" />
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.no_cancellation_policy') }}</p>
                    <x-filament::button wire:click="addNewCancellationRule" icon="heroicon-o-plus" size="sm">
                        {{ __('admin.add_rule') }}
                    </x-filament::button>
                </div>
            @endif

        </div>
    </div>

</div>

<x-filament-actions::modals />
