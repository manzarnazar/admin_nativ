@php
    use Illuminate\Support\Facades\File;

    $svgPath = resource_path('svg/sidebar/MagnifyingGlass.svg');
    $searchIcon = File::exists($svgPath) ? File::get($svgPath) : '';
@endphp

<div class="sidebar-search-wrap">
    <button
        type="button"
        class="sidebar-search-btn flex items-center gap-4 w-full px-4 py-3 bg-[#F7F7F7] border border-[#EDEDED] rounded-lg text-[#555555] text-base font-normal leading-6 transition hover:bg-[#EDEDED]"
        x-data
        x-on:click="window.dispatchEvent(new CustomEvent('open-command-palette'))"
    >
        <div class="w-[22px] h-[22px]">{!! $searchIcon !!}</div>
        <span class="sidebar-search-placeholder flex-1">{{ __('admin.search_label') }}</span>
        <div class="sidebar-search-kbd-wrap flex gap-1">
            <kbd class="bg-white border border-[#EDEDED] rounded px-1 py-1 text-xs">⌘</kbd>
            <kbd class="bg-white border border-[#EDEDED] rounded px-1 py-1 text-xs">K</kbd>
        </div>
    </button>
</div>