<x-filament-panels::page>
    {{-- Accordion sections --}}
    <div class="space-y-4" x-data="{ openSection: null }">

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- ABOUT US SECTION --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">

            {{-- Section Header --}}
            <div
                class="flex cursor-pointer items-center gap-4 px-5 py-4"
                x-on:click="openSection = openSection === 'about_us' ? null : 'about_us'"
            >
                {{-- Icon --}}
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 transition-colors duration-200 dark:bg-gray-800"
                    x-bind:style="openSection === 'about_us' ? 'background-color: var(--brand-btn);' : ''"
                >
                    <x-heroicon-o-document-text
                        class="h-5 w-5 text-gray-500 transition-colors duration-200 dark:text-gray-400"
                        x-bind:class="openSection === 'about_us' ? '!text-white' : ''"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.about_us') }}</h4>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('admin.manage_the_introduction_content_that_defines_your_brand_and_experience') }}
                    </p>
                </div>

                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800"
                    x-bind:style="openSection === 'about_us' ? 'background-color: var(--brand-btn); border-color: var(--brand-btn);' : ''"
                >
                    <x-heroicon-o-chevron-down
                        class="h-4 w-4 text-gray-500 transition-transform duration-200 dark:text-gray-400"
                        x-bind:class="openSection === 'about_us' ? 'rotate-180 !text-white' : ''"
                    />
                </div>
            </div>

            {{-- Form Content --}}
            <div x-show="openSection === 'about_us'" x-collapse>
                <div class="border-t border-gray-200 px-5 py-6 dark:border-gray-700">
                    {{ $this->aboutUsForm }}

                    <div class="mt-4">
                        <x-filament::button wire:click="saveAboutUs" size="sm" :disabled="! $this::canEdit()">
                            {{ __('admin.save_changes') }}
                        </x-filament::button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- AMENITIES & FACILITIES SECTION --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">

            {{-- Section Header --}}
            <div
                class="flex cursor-pointer items-center gap-4 px-5 py-4"
                x-on:click="openSection = openSection === 'amenities' ? null : 'amenities'"
            >
                {{-- Icon --}}
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 transition-colors duration-200 dark:bg-gray-800"
                    x-bind:style="openSection === 'amenities' ? 'background-color: var(--brand-btn);' : ''"
                >
                    <x-heroicon-o-squares-2x2
                        class="h-5 w-5 text-gray-500 transition-colors duration-200 dark:text-gray-400"
                        x-bind:class="openSection === 'amenities' ? '!text-white' : ''"
                    />
                </div>

                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.amenities_amp_facilities') }}</h4>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('admin.choose_which_amenities_should_appear_as_featured_highlights_for_guests') }}
                    </p>
                </div>

                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800"
                    x-bind:style="openSection === 'amenities' ? 'background-color: var(--brand-btn); border-color: var(--brand-btn);' : ''"
                >
                    <x-heroicon-o-chevron-down
                        class="h-4 w-4 text-gray-500 transition-transform duration-200 dark:text-gray-400"
                        x-bind:class="openSection === 'amenities' ? 'rotate-180 !text-white' : ''"
                    />
                </div>
            </div>

            {{-- Form Content --}}
            <div x-show="openSection === 'amenities'" x-collapse>
                <div class="border-t border-gray-200 px-5 py-6 dark:border-gray-700">
                    <form wire:submit="saveAmenities">
                        <div class="space-y-5">

                            {{-- Facilities Searchable Multi-Select --}}
                            <div>
                                <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    {{ __('admin.facilities') }} <span class="text-red-500">*</span>
                                </label>

                                @php
                                    $facilitiesForJs = $facilities->map(fn ($f) => [
                                        'id'       => $f->id,
                                        'name'     => $f->name,
                                        'category' => $f->category?->name ?? 'Other',
                                    ])->values()->toArray();
                                    $selectedForJs = array_values(array_map('intval', $amenities_selected));
                                @endphp

                                <div
                                    x-data="{
                                        open: false,
                                        search: '',
                                        selected: $wire.entangle('amenities_selected').live,
                                        options: @js($facilitiesForJs),
                                        get filtered() {
                                            const q = this.search.toLowerCase();
                                            return q
                                                ? this.options.filter(o => o.name.toLowerCase().includes(q))
                                                : this.options;
                                        },
                                        get groupedFiltered() {
                                            const groups = {};
                                            this.filtered.forEach(o => {
                                                if (!groups[o.category]) groups[o.category] = [];
                                                groups[o.category].push(o);
                                            });
                                            return groups;
                                        },
                                        toggle(id) {
                                            let items = [...this.selected];
                                            const idx = items.indexOf(id);
                                            if (idx >= 0) {
                                                items.splice(idx, 1);
                                            } else {
                                                items.push(id);
                                            }
                                            this.selected = items;
                                        },
                                        remove(id) {
                                            this.selected = this.selected.filter(s => s !== id);
                                        },
                                        getName(id) {
                                            return this.options.find(o => o.id === id)?.name ?? '';
                                        },
                                        isSelected(id) {
                                            return this.selected.includes(id);
                                        }
                                    }"
                                    x-on:click.outside="open = false; search = ''"
                                    class="relative"
                                >
                                    {{-- Input box with chips --}}
                                    <div
                                        @click="open = !open"
                                        class="flex min-h-[44px] cursor-pointer flex-wrap items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 shadow-sm transition focus-within:border-primary-500 focus-within:ring-1 focus-within:ring-primary-500 dark:border-gray-600 dark:bg-gray-700"
                                    >
                                        <template x-if="selected.length === 0">
                                            <span class="text-sm text-gray-400 dark:text-gray-500">{{ __('admin.select_facilities') }}</span>
                                        </template>

                                        <template x-for="id in selected" :key="id">
                                            <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2 py-0.5 text-sm font-medium text-gray-700 dark:bg-gray-600 dark:text-gray-200">
                                                <span x-text="getName(id)"></span>
                                                <button
                                                    type="button"
                                                    @click.stop="remove(id)"
                                                    class="ml-0.5 rounded text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-white"
                                                >
                                                    <x-heroicon-o-x-mark class="h-3.5 w-3.5" />
                                                </button>
                                            </span>
                                        </template>

                                        {{-- Chevron --}}
                                        <x-heroicon-s-chevron-down class="ml-auto h-4 w-4 shrink-0 text-gray-400" />
                                    </div>

                                    {{-- Dropdown panel --}}
                                    <div
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="opacity-0 scale-95"
                                        x-transition:enter-end="opacity-100 scale-100"
                                        x-transition:leave="transition ease-in duration-75"
                                        x-transition:leave-start="opacity-100 scale-100"
                                        x-transition:leave-end="opacity-0 scale-95"
                                        class="absolute z-50 mt-1 max-h-[260px] w-full overflow-y-auto overflow-hidden rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                                    >
                                        {{-- Search box --}}
                                        <div class="sticky top-0 border-b border-gray-100 bg-white px-3 py-2 dark:border-gray-700 dark:bg-gray-800">
                                            <input
                                                type="text"
                                                x-model="search"
                                                placeholder="{{ __('admin.search') }}"
                                                @click.stop
                                                class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-1.5 text-sm text-gray-900 focus:border-primary-400 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                            />
                                        </div>

                                        {{-- Grouped options --}}
                                        <template x-for="[category, items] in Object.entries(groupedFiltered)" :key="category">
                                            <div>
                                                {{-- Category header --}}
                                                <div class="px-3 py-1.5 text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500" x-text="category"></div>

                                                {{-- Options --}}
                                                <template x-for="option in items" :key="option.id">
                                                    <button
                                                        type="button"
                                                        @click.stop="toggle(option.id)"
                                                        class="flex w-full items-center gap-2.5 px-4 py-2 text-sm text-left transition hover:bg-gray-50 dark:hover:bg-gray-700"
                                                        :class="isSelected(option.id) ? 'text-primary-600 dark:text-primary-400' : 'text-gray-700 dark:text-gray-200'"
                                                    >
                                                        {{-- Checkmark --}}
                                                        <x-heroicon-s-check
                                                            x-show="isSelected(option.id)"
                                                            class="h-4 w-4 shrink-0 text-primary-500"
                                                        />
                                                        <x-heroicon-s-check
                                                            x-show="!isSelected(option.id)"
                                                            class="h-4 w-4 shrink-0 opacity-0"
                                                        />
                                                        <span x-text="option.name"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>

                                        <template x-if="Object.keys(groupedFiltered).length === 0">
                                            <p class="px-4 py-3 text-sm text-gray-400 dark:text-gray-500">{{ __('admin.no_facilities_found') }}</p>
                                        </template>
                                    </div>

                                </div>

                                @error('amenities_selected')
                                    <p class="mt-2 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Per-facility Description Cards --}}
                            @php
                                $selectedIds = collect($amenities_selected)->map(fn($id) => (int) $id);
                                $facilitiesMap = $facilities->keyBy('id');
                            @endphp

                            @if ($selectedIds->isNotEmpty())
                                <div class="space-y-4">
                                    @foreach ($selectedIds as $facilityId)
                                        @php $facility = $facilitiesMap->get($facilityId) @endphp
                                        @if ($facility)
                                            <div wire:key="amenity-desc-{{ $facilityId }}" class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800">
                                                {{-- Facility Name + Icon --}}
                                                <div class="mb-3 flex items-center gap-2">
                                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
                                                        @if ($facility->getIconUrl())
                                                            <img src="{{ $facility->getIconUrl() }}" alt="{{ $facility->name }}" class="h-5 w-5 object-contain" />
                                                        @else
                                                            <x-heroicon-o-squares-2x2 class="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                                        @endif
                                                    </div>
                                                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $facility->name }}</span>
                                                </div>

                                                {{-- Description textarea --}}
                                                <div x-data="{ count: {{ mb_strlen($amenities_descriptions[$facilityId] ?? '') }} }">
                                                    <div class="mb-1 flex items-center justify-between">
                                                        <label class="block text-xs font-medium text-gray-600 dark:text-gray-400">
                                                            {{ __('admin.description') }}
                                                        </label>
                                                        <span class="text-xs text-gray-400 dark:text-gray-500">
                                                            <span x-text="count"></span>/120
                                                        </span>
                                                    </div>
                                                    <textarea
                                                        wire:model="amenities_descriptions.{{ $facilityId }}"
                                                        rows="3"
                                                        maxlength="120"
                                                        placeholder="{{ __('admin.enter_description_here') }}"
                                                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                                        @input="count = $el.value.length"
                                                    ></textarea>
                                                    @error("amenities_descriptions.{$facilityId}")
                                                        <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
                                                    @enderror
                                                </div>
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            @endif

                        </div>

                        <div class="mt-6">
                            <x-filament::button type="submit" size="sm" :disabled="! $this::canEdit()">
                                {{ __('admin.save_changes') }}
                            </x-filament::button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════ --}}
        {{-- GUEST REVIEWS SECTION --}}
        {{-- ═══════════════════════════════════════════════════════════════ --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div
                class="flex cursor-pointer items-center gap-4 px-5 py-4"
                x-on:click="openSection = openSection === 'guest_reviews' ? null : 'guest_reviews'"
            >
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-gray-100 transition-colors duration-200 dark:bg-gray-800"
                    x-bind:style="openSection === 'guest_reviews' ? 'background-color: var(--brand-btn);' : ''"
                >
                    <x-heroicon-o-star
                        class="h-5 w-5 text-gray-500 transition-colors duration-200 dark:text-gray-400"
                        x-bind:class="openSection === 'guest_reviews' ? '!text-white' : ''"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.guest_reviews') }}</h4>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('admin.control_which_guest_reviews_are_highlighted_on_your_homepage') }}
                    </p>
                </div>
                <div
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-800"
                    x-bind:style="openSection === 'guest_reviews' ? 'background-color: var(--brand-btn); border-color: var(--brand-btn);' : ''"
                >
                    <x-heroicon-o-chevron-down
                        class="h-4 w-4 text-gray-500 transition-transform duration-200 dark:text-gray-400"
                        x-bind:class="openSection === 'guest_reviews' ? 'rotate-180 !text-white' : ''"
                    />
                </div>
            </div>

            <div x-show="openSection === 'guest_reviews'" x-collapse>
                <div class="border-t border-gray-200 px-5 py-6 dark:border-gray-700">
                    <form wire:submit="saveReviews">
                        <div class="space-y-5" x-data="{ 
                            removeReview(id) {
                                let selected = $wire.reviews_selected;
                                $wire.$set('reviews_selected', selected.filter(i => i != id));
                            }
                        }">
                            
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                @foreach($this->selectedReviewsModels as $review)
                                    <div class="relative flex flex-col justify-between rounded-xl border border-gray-200 bg-gray-50 p-5 dark:border-gray-700 dark:bg-gray-800">
                                        <div class="flex items-start justify-between">
                                            <div class="flex items-center gap-3">
                                                @if($review->user?->avatar)
                                                    <img src="{{ $review->user->getFilamentAvatarUrl() }}" class="h-10 w-10 rounded-full object-cover">
                                                @else
                                                    <div class="flex h-10 w-10 items-center justify-center rounded-full font-bold" style="background-color: var(--brand-primary-light); color: var(--brand-primary);">
                                                        {{ substr($review->user?->name ?? 'U', 0, 1) }}
                                                    </div>
                                                @endif
                                                <div>
                                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $review->user?->name }}</p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $review->user?->name ? __('admin.guest') : __('admin.anonymous') }}</p>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center gap-3">
                                                <div class="flex items-center gap-1">
                                                    <x-heroicon-s-star class="h-4 w-4 text-amber-500"/>
                                                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ number_format($review->rating, 1) }}</span>
                                                </div>
                                                <button type="button" @click="removeReview({{ $review->id }})" class="flex h-8 w-8 items-center justify-center rounded-md border border-gray-200 text-gray-400 transition hover:bg-gray-100 hover:text-red-500 dark:border-gray-600 dark:hover:bg-gray-700">
                                                    <x-phosphor-trash class="h-4 w-4"/>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <p class="mt-4 text-sm text-gray-600 line-clamp-3 dark:text-gray-300">
                                            {{ $review->review }}
                                        </p>
                                    </div>
                                @endforeach

                                {{-- "Add Reviews" card — always the last cell in the grid --}}
                                <div 
                                    class="homepage-add-review-card flex min-h-[8rem] cursor-pointer flex-col items-center justify-start rounded-2xl border-2 border-dashed px-6 pt-5 pb-5 text-center transition"
                                    x-on:click="$dispatch('open-modal', { id: 'add-reviews-modal' })"
                                >
                                    <p class="mb-4 text-sm font-medium text-gray-600 dark:text-gray-300">{{ __('admin.add_reviews_for_the_highlight_in_homepage') }}</p>
                                    <div class="inline-flex items-center gap-2 rounded-full bg-gray-900 px-5 py-2 text-sm font-medium text-white transition hover:bg-gray-700 dark:bg-white dark:text-gray-900 dark:hover:bg-gray-100">
                                        {{ __('admin.add_reviews') }}
                                        <x-heroicon-o-plus-circle class="h-5 w-5"/>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="mt-6">
                            <x-filament::button type="submit" size="sm" :disabled="! $this::canEdit()">
                                {{ __('admin.save_changes') }}
                            </x-filament::button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <x-filament::modal id="add-reviews-modal" width="4xl">
        <x-slot name="heading">
            {{ __('admin.add_reviews') }}
        </x-slot>

        <div class="mb-4 flex flex-col gap-3 sm:flex-row">
            <div class="flex-1">
                <label class="sr-only">{{ __('admin.search_reviews') }}</label>
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="reviewSearch" 
                    placeholder="{{ __('admin.eg_search_reviews') }}"
                    class="block w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
            </div>
            <div class="w-full shrink-0 sm:w-40">
                <label class="sr-only">{{ __('admin.filter_by_stars') }}</label>
                <select 
                    wire:model.live="reviewStarsFilter"
                    class="block w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-900 shadow-sm transition focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                >
                    <option value="">{{ __('admin.all_ratings') }}</option>
                    <option value="5">{{ __('admin.5_stars') }}</option>
                    <option value="4">{{ __('admin.4_stars') }}</option>
                    <option value="3">{{ __('admin.3_stars') }}</option>
                    <option value="2">{{ __('admin.2_stars') }}</option>
                    <option value="1">{{ __('admin.1_star') }}</option>
                </select>
            </div>
        </div>

        <div class="h-[400px] overflow-y-auto pr-2" x-data="{ 
            staged: $wire.entangle('reviews_selected').live
        }">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                @forelse($this->availableReviews as $availReview)
                    <div 
                        class="relative flex cursor-pointer flex-col justify-between rounded-xl border p-5 transition stage-review-card"
                        :class="staged.includes({{ $availReview->id }}) ? 'stage-review-card-active' : 'stage-review-card-inactive'"
                        @click="
                            const idx = staged.indexOf({{ $availReview->id }});
                            let temp = [...staged];
                            if (idx > -1) { temp.splice(idx, 1); } 
                            else { temp.push({{ $availReview->id }}); }
                            staged = temp;
                        "
                    >
                        <div class="absolute right-4 top-4">
                            <div 
                                class="flex h-5 w-5 items-center justify-center rounded border transition"
                                :class="staged.includes({{ $availReview->id }}) ? 'stage-review-checkbox-active' : 'stage-review-checkbox-inactive'"
                            >
                                <x-heroicon-s-check x-show="staged.includes({{ $availReview->id }})" class="h-3 w-3 text-white" />
                            </div>
                        </div>

                        <div class="flex items-start justify-between pr-8">
                            <div class="flex items-center gap-3">
                                @if($availReview->user?->avatar)
                                    <img src="{{ $availReview->user->getFilamentAvatarUrl() }}" class="h-10 w-10 rounded-full object-cover">
                                @else
                                    <div class="flex h-10 w-10 items-center justify-center rounded-full font-bold" style="background-color: var(--brand-primary-light); color: var(--brand-primary);">
                                        {{ substr($availReview->user?->name ?? 'U', 0, 1) }}
                                    </div>
                                @endif
                                <div>
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $availReview->user?->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $availReview->user?->name ? __('admin.guest') : __('admin.anonymous') }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 flex items-center justify-between">
                            <p class="text-xs text-gray-600 line-clamp-2 pr-4 flex-1 dark:text-gray-300">
                                {{ $availReview->review }}
                            </p>
                            <div class="flex shrink-0 items-center gap-1">
                                <x-heroicon-s-star class="h-4 w-4 text-amber-500"/>
                                <span class="text-sm font-medium text-gray-900 dark:text-white">{{ number_format($availReview->rating, 1) }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-1 py-10 text-center text-sm text-gray-500 lg:col-span-2">
                        {{ __('admin.no_reviews_found_matching_your_search') }}
                    </div>
                @endforelse
            </div>
        </div>

        <x-slot name="footerActions">
            <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'add-reviews-modal' })">
                {{ __('admin.cancel') }}
            </x-filament::button>
            <x-filament::button color="primary" x-on:click="$dispatch('close-modal', { id: 'add-reviews-modal' })">
                {{ __('admin.add_reviews') }}
            </x-filament::button>
        </x-slot>
    </x-filament::modal>

    <x-filament-actions::modals />
</x-filament-panels::page>
