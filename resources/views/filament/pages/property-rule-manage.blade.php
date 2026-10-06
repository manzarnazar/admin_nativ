<x-filament-panels::page>
    @if ($this->hasPropertyRules())
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->getPropertyRules() as $rule)
                <div class="rule-card relative rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-600">
                    {{-- Hover Actions --}}
                    <div class="rule-card-actions absolute right-3 top-3 items-center gap-2">
                        {{ ($this->editRuleAction)(['rule' => $rule->id]) }}
                        {{ ($this->deleteRuleAction)(['rule' => $rule->id]) }}
                    </div>

                    {{-- Rule Icon --}}
                    <div class="mb-3 flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-950">
                        <img
                            src="{{ Storage::disk('public')->url($rule->icon) }}"
                            alt="{{ $rule->name }}"
                            class="h-6 w-6 object-contain"
                        />
                    </div>

                    {{-- Rule Name --}}
                    <h4 class="rule-card-title text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $rule->name }}
                    </h4>

                    {{-- Description --}}
                    <p class="mt-1 line-clamp-2 text-xs leading-relaxed text-gray-500 dark:text-gray-400">
                        {{ $rule->description }}
                    </p>

                    {{-- Divider --}}
                    <hr class="mt-4 border-gray-200 dark:border-gray-700" />

                    {{-- Footer: Question Count + Status --}}
                    <div class="mt-3 flex items-center justify-between">
                        <div class="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <x-heroicon-o-list-bullet class="h-4 w-4" />
                            {{ $rule->questions_count }} {{ $rule->questions_count > 1 ? __('admin.questions') : __('admin.question') }}
                        </div>

                        @if ($rule->status === \App\Enums\PropertyRuleStatus::Active)
                            <span class="inline-flex rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-400">
                                {{ __('admin.active') }}
                            </span>
                        @else
                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                {{ __('admin.inactive') }}
                            </span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                    <x-heroicon-o-list-bullet class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                </div>

                <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                    {{ __('admin.no_property_rules_added') }}
                </h4>
                <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.no_property_rules_description') }}
                </p>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</x-filament-panels::page>
