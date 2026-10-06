<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div class="flex w-full gap-1 p-1 rounded-xl dark:bg-gray-800" style="background-color: var(--brand-primary-light);">
        @foreach (App\Enums\DisplayPlatform::cases() as $platform)
            <button
                type="button"
                wire:click="$set('{{ $getStatePath() }}', '{{ $platform->value }}')"
                class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $getState() === $platform->value ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
            >
                @if ($platform->value === 'web')
                    <x-heroicon-o-computer-desktop class="h-4 w-4" />
                @elseif ($platform->value === 'app')
                    <x-heroicon-o-device-phone-mobile class="h-4 w-4" />
                @else
                    <x-heroicon-o-globe-alt class="h-4 w-4" />
                @endif
                {{ $platform->getLabel() }}
            </button>
        @endforeach
    </div>
</x-dynamic-component>
