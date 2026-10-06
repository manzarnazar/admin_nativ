{{--
    Shared "Property Rules" tab body. Expects:
    - $property
    - $cancellationPolicy (?CancellationPolicy) — resolved via CancellationPolicyService
    - $ruleGroups (array) — dynamic PropertyRule categories + this property's
      PropertyRuleAnswer values; whatever admin has configured at
      /property-rules shows up here automatically, nothing is hardcoded.
--}}
<div class="space-y-4">

    {{-- Properties Rule card: heading + check-in/out times --}}
    <div class="rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
        <h3 class="mb-4 text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.properties_rules') }}</h3>

        <div class="grid grid-cols-2 gap-4">
            <div class="flex items-center gap-4 rounded-xl bg-green-50 px-5 py-4 dark:bg-green-950/30">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-500 dark:bg-green-600">
                    <x-heroicon-o-clock class="h-5 w-5 text-white" />
                </div>
                <div>
                    <p class="text-xs font-medium text-green-600 dark:text-green-400">{{ __('admin.check_in_time') }}</p>
                    <p class="text-lg font-bold text-green-700 dark:text-green-300">
                        {{ $property->check_in_time ? \Carbon\Carbon::parse($property->check_in_time)->format('h:i A') : '--' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-4 rounded-xl bg-red-50 px-5 py-4 dark:bg-red-950/30">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-500 dark:bg-red-600">
                    <x-heroicon-o-clock class="h-5 w-5 text-white" />
                </div>
                <div>
                    <p class="text-xs font-medium text-red-600 dark:text-red-400">{{ __('admin.check_out_time') }}</p>
                    <p class="text-lg font-bold text-red-700 dark:text-red-300">
                        {{ $property->check_out_time ? \Carbon\Carbon::parse($property->check_out_time)->format('h:i A') : '--' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- All rule accordions in one card --}}
    @php
        $hasCancellation = $cancellationPolicy && $cancellationPolicy->rules->isNotEmpty();
        $hasRules = count($ruleGroups) > 0;
    @endphp

    @if ($hasCancellation || $hasRules)
        <div class="rounded-2xl border border-[#EDEDED] bg-white dark:border-gray-700 dark:bg-gray-900 divide-y divide-[#EDEDED] dark:divide-gray-700">

            {{-- Cancellation Policy --}}
            @if ($hasCancellation)
                <div x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-6 py-4">
                        <div class="flex items-center gap-3">
                            <x-heroicon-o-shield-check class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                            <div class="text-left">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.cancellation_policy') }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.rules_regarding_booking_cancellations_refunds_and_noshows') }}</p>
                            </div>
                        </div>
                        <x-heroicon-o-chevron-down
                            class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200"
                            x-bind:class="{ 'rotate-180': open }"
                        />
                    </button>
                    <div x-show="open" x-collapse class="border-t border-[#EDEDED] px-6 py-4 dark:border-gray-700">
                        @if ($property->cancellation_policy_source === \App\Enums\PropertyCancellationPolicySource::Custom)
                            <span class="mb-3 inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-medium text-blue-600 dark:bg-blue-950/30 dark:text-blue-400">
                                <x-heroicon-s-check-circle class="h-3.5 w-3.5" />
                                {{ __('admin.partner_custom_policy_applied') }}
                            </span>
                        @else
                            <span class="mb-3 inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                                <x-heroicon-s-check-circle class="h-3.5 w-3.5" />
                                {{ __('admin.platform_default_policy_applied') }}
                            </span>
                        @endif

                        <p class="mb-3 text-xs font-semibold text-gray-700 dark:text-gray-300">{{ __('admin.review_refund_eligibility') }}</p>
                        <ul class="space-y-2">
                            @foreach ($cancellationPolicy->rules->sortByDesc('days_before_checkin') as $rule)
                                <li class="flex items-center justify-between text-sm">
                                    <span class="text-gray-600 dark:text-gray-400">
                                        @if ($rule->refund_percentage > 0)
                                            {{ $rule->refund_percentage === 100 ? __('admin.free_cancellation') : __('admin.partial_refund', ['percentage' => $rule->refund_percentage]) }}
                                        @else
                                            {{ __('admin.non_refundable') }}
                                        @endif
                                    </span>
                                    <span class="font-medium text-gray-950 dark:text-white">
                                        @if ($rule->days_before_checkin === 0)
                                            {{ __('admin.on_day_of_checkin') }}
                                        @else
                                            {{ $rule->days_before_checkin }}+ {{ __('admin.days_before') }}
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            {{-- Rule Answers — dynamic PropertyRule categories --}}
            @foreach ($ruleGroups as $group)
                <div x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between px-6 py-4">
                        <div class="flex items-center gap-3">
                            @if (! empty($group['icon']))
                                <img src="{{ asset('storage/'.$group['icon']) }}" class="h-5 w-5 object-contain" alt="">
                            @else
                                <x-heroicon-o-clipboard-document-list class="h-5 w-5 text-gray-400 dark:text-gray-500" />
                            @endif
                            <div class="text-left">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $group['name'] }}</p>
                                @if (! empty($group['description']))
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $group['description'] }}</p>
                                @endif
                            </div>
                        </div>
                        <x-heroicon-o-chevron-down
                            class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200"
                            x-bind:class="{ 'rotate-180': open }"
                        />
                    </button>
                    <div x-show="open" x-collapse class="border-t border-[#EDEDED] px-6 py-4 dark:border-gray-700">
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
                    </div>
                </div>
            @endforeach

        </div>
    @endif

    {{-- Custom Rules --}}
    @if ($property->custom_rules)
        <div class="rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h4 class="flex items-center gap-2 text-sm font-semibold text-gray-950 dark:text-white">
                <x-heroicon-o-pencil-square class="h-4 w-4" />
                {{ __('admin.add_custom_rules') }}
            </h4>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $property->custom_rules }}</p>
        </div>
    @endif

</div>
