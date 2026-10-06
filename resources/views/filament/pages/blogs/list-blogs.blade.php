<x-filament-panels::page>
    {{-- Tab Switcher --}}
    <div class="flex items-center justify-between">
        <div class="inline-flex gap-1 p-2 rounded-xl p-1 dark:bg-gray-800" style="background-color: var(--brand-primary-light);">
            <button
                wire:click="switchTab('blogs')"
                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $currentTab === 'blogs' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
            >
                <x-heroicon-o-queue-list class="h-4 w-4" />
                {{ __('admin.all_blogs') }}
            </button>

            <button
                wire:click="switchTab('categories')"
                class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
                style="{{ $currentTab === 'categories' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
            >
                <x-heroicon-o-tag class="h-4 w-4" />
                {{ __('admin.categories') }}
            </button>
        </div>

        @if ($currentTab === 'blogs' && ! $this->getHasBlogs())
            <a href="{{ \App\Filament\Resources\Blogs\BlogResource::getUrl('create') }}" wire:navigate>
                <x-filament::button>
                    + Create New Blog
                </x-filament::button>
            </a>
        @endif
    </div>

    {{-- Tab Content --}}
    @if ($currentTab === 'blogs')
        @if ($this->getHasBlogs())
            {{ $this->table }}
        @else
            <div class="fi-ta-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex w-full flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800">
                        <x-heroicon-o-queue-list class="h-6 w-6 text-gray-400 dark:text-gray-500" />
                    </div>

                    <h4 class="text-base font-semibold text-gray-950 dark:text-white">
                        {{ __('admin.no_blogs_published_yet') }}
                    </h4>
                    <p class="max-w-md text-sm text-gray-500 dark:text-gray-400">
                        Start creating content to educate users, improve SEO, and promote your platform.
                        Your published blogs will appear here once added.
                    </p>
                </div>
            </div>
        @endif
    @else
        @livewire('blog-category-table', key('blog-category-table'))
    @endif
</x-filament-panels::page>
