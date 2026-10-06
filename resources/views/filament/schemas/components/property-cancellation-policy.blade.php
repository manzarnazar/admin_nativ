@php
    $policy = $this->getCancellationPolicy();
    $rules = $policy?->rules ?? collect();
@endphp

<div class="space-y-4">
    @if ($rules->count() > 0)
        <div class="space-y-4">
            @foreach ($rules as $index => $rule)
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    {{-- Rule Header --}}
                    <div class="mb-4 flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-600 text-xs font-bold text-white">
                            {{ $index + 1 }}
                        </span>
                        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">
                            {{ __('admin.rule_phase', ['number' => $index + 1]) }}
                        </h4>
                    </div>

                    {{-- Rule Fields (read-only) --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- Days Before Check-in --}}
                        <div>
                            <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                {{ __('admin.days_before_checkin') }}
                            </label>
                            <div class="flex items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 dark:border-gray-600 dark:bg-gray-800">
                                <x-heroicon-o-calendar class="h-4 w-4 text-gray-400" />
                                <span class="text-sm text-gray-900 dark:text-white">{{ $rule->days_before_checkin }}</span>
                            </div>
                        </div>

                        {{-- Is Refundable --}}
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

                        {{-- Refund Percentage --}}
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

                    {{-- Preview --}}
                    <div class="mt-4 flex items-center gap-2 rounded-lg bg-primary-50 px-4 py-2.5 dark:bg-primary-950">
                        <x-heroicon-s-chat-bubble-left-right class="h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400" />
                        <span class="text-sm text-primary-700 dark:text-primary-300">
                            <span class="font-semibold">{{ __('admin.preview') }}:</span>
                            @if ($rule->days_before_checkin === 0)
                                {{ __('admin.preview_no_show', ['percentage' => $rule->refund_percentage]) }}
                            @else
                                {{ __('admin.preview_cancellation', ['days' => $rule->days_before_checkin, 'refundable' => $rule->refund_percentage > 0 ? __('admin.yes_refundable') : __('admin.non_refundable')]) }}
                            @endif
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- Empty State --}}
        <div class="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-200 px-6 py-12 text-center dark:border-gray-700">
            <x-heroicon-o-document-text class="h-8 w-8 text-gray-400 dark:text-gray-500" />
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                {{ __('admin.no_cancellation_policy') }}
            </p>
            <p class="max-w-sm text-xs text-gray-400 dark:text-gray-500">
                {{ __('admin.no_cancellation_policy_description') }}
            </p>
        </div>
    @endif
</div>
