<div
    x-data="{
        open: false,
        activeIndex: -1,
        openPalette() {
            this.open = true;
            this.activeIndex = -1;
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },
        closePalette() {
            this.open = false;
            this.activeIndex = -1;
            $wire.set('search', '');
        },
        moveDown() {
            const items = this.$refs.results?.querySelectorAll('[data-item]') ?? [];
            if (!items.length) return;
            this.activeIndex = (this.activeIndex + 1) % items.length;
            items[this.activeIndex]?.scrollIntoView({ block: 'nearest' });
        },
        moveUp() {
            const items = this.$refs.results?.querySelectorAll('[data-item]') ?? [];
            if (!items.length) return;
            this.activeIndex = (this.activeIndex <= 0 ? items.length : this.activeIndex) - 1;
            items[this.activeIndex]?.scrollIntoView({ block: 'nearest' });
        },
        confirmSelection() {
            const items = this.$refs.results?.querySelectorAll('[data-item]') ?? [];
            if (this.activeIndex >= 0 && items[this.activeIndex]) {
                items[this.activeIndex].click();
            }
        },
    }"
    x-init="
        if (!window._cpKeySetup) {
            window._cpKeySetup = true;
            window.addEventListener('keydown', e => {
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    window.dispatchEvent(new CustomEvent('open-command-palette'));
                }
            });
        }
    "
    @open-command-palette.window="openPalette()"
    @keydown.window.escape="if (open) closePalette()"
    @keydown.window.arrow-down.prevent="if (open) moveDown()"
    @keydown.window.arrow-up.prevent="if (open) moveUp()"
    @keydown.window.enter.prevent="if (open) confirmSelection()"
>
    {{-- Backdrop --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="closePalette()"
        class="cp-backdrop"
        style="display:none"
    ></div>

    {{-- Modal --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="cp-modal"
        @click.self="closePalette()"
        style="display:none"
    >
        <div class="cp-box">
            {{-- Search Header --}}
            <div class="cp-header">
                <svg class="cp-search-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input
                    x-ref="searchInput"
                    wire:model.live.debounce.100ms="search"
                    type="text"
                    class="cp-input"
                    placeholder="{{ __('admin.search_pages_placeholder') }}"
                    @keydown.escape.prevent="closePalette()"
                    @keydown.arrow-down.prevent="moveDown()"
                    @keydown.arrow-up.prevent="moveUp()"
                    @keydown.enter.prevent="confirmSelection()"
                    @keydown.stop
                />
                <kbd class="cp-esc" @click="closePalette()">{{ __('admin.esc_key') }}</kbd>
            </div>

            {{-- Results --}}
            <div x-ref="results" class="cp-results">
                @if (count($this->results) > 0)
                    @php $idx = 0; @endphp
                    @foreach ($this->results as $group)
                        <div class="cp-group">
                            <div class="cp-group-label">{{ $group['label'] }}</div>
                            @foreach ($group['items'] as $item)
                                @php $currentIdx = $idx++; @endphp
                                <a
                                    href="{{ $item['url'] }}"
                                    wire:navigate
                                    data-item
                                    :class="{ 'cp-item-active': activeIndex === {{ $currentIdx }} }"
                                    class="cp-item {{ $item['is_child'] ? 'cp-item-child' : '' }}"
                                    @click="closePalette()"
                                    @mouseenter="activeIndex = {{ $currentIdx }}"
                                >
                                    @if ($item['icon'])
                                        <x-filament::icon :icon="$item['icon']" class="cp-item-icon" />
                                    @else
                                        <span class="cp-item-icon-empty"></span>
                                    @endif
                                    <span class="cp-item-label">{{ $item['label'] }}</span>
                                    <svg class="cp-item-arrow" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                    </svg>
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                @elseif ($search !== '')
                    <div class="cp-empty">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        {{ __('admin.no_results_for') }} "<strong>{{ $search }}</strong>"
                    </div>
                @else
                    <div class="cp-empty">{{ __('admin.no_recent_searches') }}</div>
                @endif
            </div>

            {{-- Hint bar --}}
            <div class="cp-hint-bar">
                <span><kbd>↑</kbd><kbd>↓</kbd> {{ __('admin.navigate_hint') }}</span>
                <span><kbd>↵</kbd> {{ __('admin.select_hint') }}</span>
                <span><kbd>{{ __('admin.esc_key') }}</kbd> {{ __('admin.close') }}</span>
            </div>
        </div>
    </div>
</div>
