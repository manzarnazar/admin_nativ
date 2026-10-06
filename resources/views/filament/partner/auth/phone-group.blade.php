<x-filament-forms::field-wrapper
    id="phone_group"
    :label="__('admin.phone_number')"
    :required="true"
    :state-path="$getStatePath()"
>
    <div class="flex items-center rounded-lg shadow-sm ring-1 ring-gray-950/10 focus-within:ring-2 focus-within:ring-primary-600 dark:ring-white/20 dark:focus-within:ring-primary-500 bg-[#f9fafb] dark:bg-white/5 relative">
        <div class="w-[110px] joined-select-container flex-shrink-0">
            {{ $getChildComponentContainer()->getComponents()[0] }}
        </div>
        <div class="w-px h-6 bg-gray-300 dark:bg-gray-600 flex-shrink-0"></div>
        <div class="flex-1 joined-input-container">
            {{ $getChildComponentContainer()->getComponents()[1] }}
        </div>
    </div>
</x-filament-forms::field-wrapper>
