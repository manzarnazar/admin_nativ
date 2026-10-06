<x-filament-panels::page>
    {{-- Back Link + Step Counter --}}
    <div class="-mt-4 mb-4 flex items-center justify-between">
        <a
            href="{{ \App\Filament\Pages\PropertyManage::getUrl() }}"
            wire:navigate
            class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back_to_property_manage') }}
        </a>
        <span class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('admin.step_x_of_y', ['current' => $this->currentStep, 'total' => $this->totalSteps]) }}
        </span>
    </div>

    {{-- Gray Section: Sidebar + Content --}}
    <div class="section-bg-gray flex flex-col lg:flex-row gap-4 lg:gap-6 -mx-8 -mb-8 px-8 pt-6 pb-8"
         x-data="{ mobileSidebarOpen: false }">

        {{-- Left Sidebar: Setup Progress --}}
        <div class="lg:w-72 lg:shrink-0">
            <div class="lg:sticky lg:top-4 rounded-2xl bg-white border border-[#EDEDED] p-6 dark:bg-gray-900 dark:border-gray-700">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400">
                        {{ __('admin.setup_progress') }}
                    </h3>
                    {{-- Mobile-only toggle --}}
                    <button
                        type="button"
                        x-on:click="mobileSidebarOpen = !mobileSidebarOpen"
                        class="lg:hidden flex items-center gap-1.5 text-sm font-medium text-primary-600"
                    >
                        <span x-text="mobileSidebarOpen ? '{{ __('admin.hide') }}' : '{{ __('admin.view_steps') }}'"></span>
                        <svg x-bind:class="{ 'rotate-180': mobileSidebarOpen }" class="h-4 w-4 transition-transform duration-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                </div>
                <hr class="my-4 border-[#EDEDED] dark:border-gray-700" />

                <nav class="property-wizard-steps flex flex-col"
                     x-bind:class="{ 'mobile-open': mobileSidebarOpen }">
                    @for ($step = 1; $step <= $this->totalSteps; $step++)
                        @php
                            $status = $this->getStepStatus($step);
                            $isClickable = $status === 'completed' || $status === 'current';
                            $isLast = $step === $this->totalSteps;
                        @endphp

                        <div class="flex">
                            {{-- Left: Icon + Connector Line --}}
                            <div class="flex flex-col items-center">
                                <button
                                    type="button"
                                    @if ($isClickable) wire:click="goToStep({{ $step }})" @endif
                                    class="{{ $isClickable ? 'cursor-pointer' : 'cursor-not-allowed' }}"
                                >
                                    @if ($status === 'completed')
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-600 text-white">
                                            <x-heroicon-s-check class="h-3.5 w-3.5" />
                                        </span>
                                    @elseif ($status === 'current')
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-primary-600 bg-white dark:bg-gray-900">
                                            <span class="h-2 w-2 rounded-full bg-primary-600"></span>
                                        </span>
                                    @else
                                        <span class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-gray-300 dark:border-gray-600"></span>
                                    @endif
                                </button>

                                @unless ($isLast)
                                    <div @class([
                                        'w-0.5 flex-1 my-1',
                                        'bg-primary-600' => $status === 'completed',
                                        'bg-gray-200 dark:bg-gray-700' => $status !== 'completed',
                                    ]) style="min-height: 20px;"></div>
                                @endunless
                            </div>

                            {{-- Right: Step Label --}}
                            <button
                                type="button"
                                @if ($isClickable) wire:click="goToStep({{ $step }})" @endif
                                @class([
                                    'ml-3 pb-4 text-left text-sm transition',
                                    'cursor-pointer' => $isClickable,
                                    'cursor-not-allowed' => !$isClickable,
                                ])
                            >
                                <span @class([
                                    'text-gray-950 dark:text-white',
                                    'font-semibold' => $status === 'current' || $status === 'completed',
                                ])>
                                    {{ $this->getStepLabel($step) }}
                                </span>
                            </button>
                        </div>
                    @endfor
                </nav>
            </div>
        </div>

        {{-- Right Content: Step Form --}}
        <div class="min-w-0 flex-1">
            <div class="rounded-2xl bg-white border border-[#EDEDED] overflow-hidden dark:bg-gray-900 dark:border-gray-700">
                {{-- Step Header --}}
                <div class="border-b border-[#EDEDED] px-6 py-5 dark:border-gray-700 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $this->getStepLabel($this->currentStep) }}
                        </h2>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('admin.wizard_step_' . $this->currentStep . '_description') }}
                        </p>
                    </div>

                    @if ($this->currentStep === 3 && ! $this->roomFormMode)
                        <x-filament::button icon="heroicon-o-plus" wire:click="showAddRoomForm" class="shrink-0">
                            {{ __('admin.add_room') }}
                        </x-filament::button>
                    @endif
                </div>

                {{-- Form Content --}}
                <div class="property-wizard-form p-6 flex flex-col gap-6">
                    {{ $this->form }}
                </div>

                {{-- Navigation Buttons --}}
                <div class="border-t border-[#EDEDED] px-6 py-4 flex items-center justify-between gap-3 dark:border-gray-700">
                    <div>
                        @if ($this->currentStep > 1)
                            <x-filament::button
                                wire:click="previousStep"
                                wire:loading.attr="disabled"
                                wire:target="previousStep,saveDraft,nextStep,submitProperty"
                                color="gray"
                                icon="heroicon-o-arrow-left"
                                icon-position="before"
                            >
                                {{ __('admin.previous') }}
                            </x-filament::button>
                        @endif
                    </div>

                    <div class="flex items-center gap-3">
                        <x-filament::button
                            wire:click="saveDraft"
                            wire:loading.attr="disabled"
                            wire:target="previousStep,saveDraft,nextStep,submitProperty"
                            color="gray"
                            icon="heroicon-o-document"
                            icon-position="before"
                        >
                            {{ __('admin.save_draft') }}
                        </x-filament::button>

                        @if ($this->currentStep < $this->totalSteps)
                            <x-filament::button
                                wire:click="nextStep"
                                wire:loading.attr="disabled"
                                wire:target="previousStep,saveDraft,nextStep,submitProperty"
                                icon="heroicon-o-arrow-right"
                                icon-position="after"
                            >
                                {{ __('admin.next') }}
                            </x-filament::button>
                        @else
                            <x-filament::button
                                wire:click="submitProperty"
                                wire:loading.attr="disabled"
                                wire:target="previousStep,saveDraft,nextStep,submitProperty"
                                icon="heroicon-o-arrow-right"
                                icon-position="after"
                            >
                                {{ __('admin.submit_property') }}
                            </x-filament::button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
