@php
    $isContained = $isContained();
    $key = $getKey();
    $previousAction = $getAction('previous');
    $nextAction = $getAction('next');
    $steps = $getChildSchema()->getComponents();
    $isHeaderHidden = $isHeaderHidden();
@endphp

<div
    x-load
    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('wizard', 'filament/schemas') }}"
    x-data="wizardSchemaComponent({
                isSkippable: @js($isSkippable()),
                isStepPersistedInQueryString: @js($isStepPersistedInQueryString()),
                key: @js($key),
                startStep: @js($getStartStep()),
                stepQueryStringKey: @js($getStepQueryStringKey()),
            })"
    x-on:next-wizard-step.window="if ($event.detail.key === @js($key)) goToNextStep()"
    x-on:go-to-wizard-step.window="$event.detail.key === @js($key) && goToStep($event.detail.step)"
    wire:ignore.self
    {{
        $attributes
            ->merge([
                'id' => $getId(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)
            ->merge($getExtraAlpineAttributes(), escape: false)
            ->class([
                'fi-sc-wizard',
                'fi-contained' => $isContained,
                'fi-sc-wizard-header-hidden' => $isHeaderHidden,
            ])
    }}
>
    <input
        type="hidden"
        value="{{
            collect($steps)
                ->filter(static fn (\Filament\Schemas\Components\Wizard\Step $step): bool => $step->isVisible())
                ->map(static fn (\Filament\Schemas\Components\Wizard\Step $step): ?string => $step->getKey())
                ->values()
                ->toJson()
        }}"
        x-ref="stepsData"
    />

    @if (! $isHeaderHidden)
        <div
            @if (filled($label = $getLabel()))
                aria-label="{{ $label }}"
            @endif
            role="list"
            x-cloak
            x-ref="header"
            class="self-stretch border-t-2 border-b-2 border-gray-200 dark:border-white/10 flex flex-wrap justify-start items-center w-full mb-6"
        >
            @foreach ($steps as $step)
                <button
                    type="button"
                    x-bind:aria-current="getStepIndex(step) === {{ $loop->index }} ? 'step' : null"
                    x-on:click="step = @js($step->getKey())"
                    x-bind:disabled="! isStepAccessible(@js($step->getKey())) || @js($previousAction->isDisabled())"
                    class="p-4 flex justify-start items-center gap-4 transition-colors relative"
                    x-bind:class="{
                        'bg-gray-50 dark:bg-white/5 border-b-2 border-primary-600 -mb-[2px]': getStepIndex(step) === {{ $loop->index }},
                        'hover:bg-gray-50 dark:hover:bg-white/5': getStepIndex(step) !== {{ $loop->index }} && isStepAccessible(@js($step->getKey())),
                        'opacity-50 cursor-not-allowed': ! isStepAccessible(@js($step->getKey())) && getStepIndex(step) !== {{ $loop->index }}
                    }"
                >
                    <div class="flex justify-start items-center gap-4">
                        <div 
                            class="w-6 h-6 flex items-center justify-center transition-colors"
                            x-bind:class="{
                                'text-primary-600': getStepIndex(step) === {{ $loop->index }},
                                'text-gray-700 dark:text-gray-400': getStepIndex(step) !== {{ $loop->index }},
                            }"
                        >
                            @if (filled($icon = $step->getIcon()))
                                {{
                                    \Filament\Support\generate_icon_html(
                                        $icon,
                                        attributes: new \Illuminate\View\ComponentAttributeBag([
                                            'class' => 'w-5 h-5',
                                        ]),
                                    )
                                }}
                            @else
                                <span class="text-base font-medium">{{ $loop->index + 1 }}</span>
                            @endif
                        </div>
                        <div 
                            class="justify-start text-base font-medium leading-6 transition-colors whitespace-nowrap"
                            x-bind:class="{
                                'text-primary-600': getStepIndex(step) === {{ $loop->index }},
                                'text-gray-950 dark:text-gray-100': getStepIndex(step) !== {{ $loop->index }},
                            }"
                        >
                            {{ $step->getLabel() }}
                        </div>
                    </div>
                </button>
            @endforeach
        </div>
    @endif

    @foreach ($steps as $step)
        {{ $step }}
    @endforeach

    <div x-cloak class="fi-sc-wizard-footer">
        <div
            x-cloak
            @if (! $previousAction->isDisabled())
                x-on:click="goToPreviousStep"
            @endif
            x-show="! isFirstStep()"
        >
            {{ $previousAction }}
        </div>

        <div x-show="isFirstStep()">
            {{ $getCancelAction() }}
        </div>

        <div
            x-cloak
            @if (! $nextAction->isDisabled())
                x-on:click="requestNextStep()"
            @endif
            x-bind:class="{ 'fi-hidden': isLastStep() }"
            wire:loading.class="fi-disabled"
        >
            {{ $nextAction }}
        </div>

        <div x-bind:class="{ 'fi-hidden': ! isLastStep() }">
            {{ $getSubmitAction() }}
        </div>
    </div>
</div>
