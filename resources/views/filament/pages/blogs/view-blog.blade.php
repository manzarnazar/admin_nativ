<div class="mx-auto max-w-2xl space-y-6">
    {{-- Cover Image --}}
    @if ($blog->cover_image)
        <div class="overflow-hidden rounded-xl">
            <img
                src="{{ Storage::disk('public')->url($blog->cover_image) }}"
                alt="{{ $blog->title }}"
                class="h-64 w-full object-cover"
            />
        </div>
    @endif

    {{-- Meta Info --}}
    <div class="flex flex-wrap items-center justify-center gap-4 text-sm text-gray-500 dark:text-gray-400">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-400">
            {{ $blog->category?->name ?? 'Uncategorized' }}
        </span>
        <span>{{ $blog->created_at->format('d M Y') }}</span>
        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $blog->status->value === 'published' ? 'bg-green-50 text-green-700 dark:bg-green-500/10 dark:text-green-400' : 'bg-gray-100 text-gray-700 dark:bg-gray-500/10 dark:text-gray-400' }}">
            {{ $blog->status->label() }}
        </span>
        @if ($blog->published_at)
            <span>{{ __('admin.published') }}: {{ $blog->published_at->setTimezone(\App\Support\UserTimezone::current())->format('d M Y, h:i A') }} {{ \App\Support\UserTimezone::abbreviation() }}</span>
        @endif
    </div>

    {{-- Short Description --}}
    <div class="text-center">
        <h4 class="text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.short_description') }}</h4>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $blog->short_description }}</p>
    </div>

    {{-- Main Content --}}
    <div>
        <h4 class="text-center text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.content') }}</h4>
        <div class="prose prose-sm dark:prose-invert mt-2 mx-auto max-w-none">
            {!! $blog->content !!}
        </div>
    </div>

    {{-- SEO Info --}}
    <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
        <h4 class="text-center text-sm font-semibold text-gray-950 dark:text-white">{{ __('admin.seo_configuration') }}</h4>
        <div class="mt-3 space-y-2 text-center text-sm">
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('admin.meta_title') }}</span>
                <span class="text-gray-600 dark:text-gray-400">{{ $blog->meta_title }}</span>
            </div>
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('admin.meta_description') }}</span>
                <span class="text-gray-600 dark:text-gray-400">{{ $blog->meta_description }}</span>
            </div>
            <div>
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ __('admin.keywords') }}</span>
                <span class="text-gray-600 dark:text-gray-400">{{ $blog->keywords }}</span>
            </div>
        </div>
    </div>
</div>
