@php
    $ruleGroups = $this->getPropertyRuleAnswers();
    $policy = $this->getCancellationPolicy();
@endphp

<div class="space-y-6">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.properties_rules') }}</h3>

    {{-- Check-in / Check-out Times --}}
    <div class="grid grid-cols-2 gap-4">
        <div class="flex items-center gap-3 rounded-lg bg-green-50 px-5 py-4 dark:bg-green-950/30">
            <x-heroicon-o-clock class="h-5 w-5 text-green-600 dark:text-green-400" />
            <div>
                <p class="text-xs text-green-600 dark:text-green-400">{{ __('admin.standard_check_in_time') }}</p>
                <p class="text-lg font-bold text-green-700 dark:text-green-300">
                    {{ $property->check_in_time ? \Carbon\Carbon::parse($property->check_in_time)->format('h:i A') : '--' }}
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-lg bg-red-50 px-5 py-4 dark:bg-red-950/30">
            <x-heroicon-o-clock class="h-5 w-5 text-red-600 dark:text-red-400" />
            <div>
                <p class="text-xs text-red-600 dark:text-red-400">{{ __('admin.standard_check_out_time') }}</p>
                <p class="text-lg font-bold text-red-700 dark:text-red-300">
                    {{ $property->check_out_time ? \Carbon\Carbon::parse($property->check_out_time)->format('h:i A') : '--' }}
                </p>
            </div>
        </div>
    </div>

    {{-- Cancellation Policy --}}
    @if ($policy && $policy->rules->isNotEmpty())
        <x-filament::section
            icon="heroicon-o-shield-check"
            :heading="__('admin.cancellation_policy')"
            :description="__('admin.rules_regarding_booking_cancellations_refunds_and_noshows')"
            collapsible
            collapsed
        >
            <p class="mb-3 text-xs font-semibold text-gray-700 dark:text-gray-300">{{ __('admin.review_refund_eligibility') }}</p>
            <ul class="space-y-2">
                @foreach ($policy->rules->sortByDesc('days_before_checkin') as $rule)
                    <li class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">
                            @if ($rule->refund_percentage > 0)
                                {{ $rule->refund_percentage === 100 ? __('admin.free_cancellation') : __('admin.partial_refund', ['percentage' => $rule->refund_percentage]) }}
                            @else
                                {{ __('admin.non_refundable') }}
                            @endif
                        </span>
                        <span class="text-gray-500 dark:text-gray-400">
                            @if ($rule->days_before_checkin === 0)
                                {{ __('admin.on_day_of_checkin') }}
                            @else
                                {{ $rule->days_before_checkin }}+ {{ __('admin.days_before') }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    {{-- Rule Answers --}}
    @foreach ($ruleGroups as $group)
        <x-filament::section
            :icon="$group['icon'] ? asset('storage/' . $group['icon']) : 'heroicon-o-clipboard-document-list'"
            :heading="$group['name']"
            collapsible
            collapsed
        >
            <ul class="space-y-3">
                @foreach ($group['questions'] as $qa)
                    <li class="flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-400">{{ $qa['question'] }}</span>
                        <span class="font-medium text-gray-950 dark:text-white">
                            @if ($qa['answer_type'] === 'yes_no')
                                {{ $qa['answer'] === 'Yes' ? __('admin.yes') : __('admin.no') }}
                            @elseif (is_array($qa['answer']))
                                {{ implode(', ', $qa['answer']) }}
                            @else
                                {{ $qa['answer'] ?? '-' }}
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endforeach

    {{-- Custom Rules --}}
    @if ($property->custom_rules)
        <div class="rounded-lg bg-gray-50 p-5 dark:bg-gray-800">
            <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                <x-heroicon-o-pencil-square class="h-4 w-4" />
                {{ __('admin.add_custom_rules') }}
            </h4>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $property->custom_rules }}</p>
        </div>
    @endif
</div>
