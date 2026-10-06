{{--
    Single media group: gray pill header + white card grid, with an Alpine
    "Show More Photos" reveal past the first row (4 items). Shared by both
    the mandatory Primary Showcase section and each gallery group in
    tab-media.blade.php. Expects: $heading, $count, $images (Collection<PropertyImage>).
--}}
<div>
    <div class="mb-3 rounded-lg bg-gray-100 px-4 py-2 dark:bg-gray-700">
        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $heading }} ({{ $count }})</span>
    </div>

    <div x-data="{ expanded: false }" class="rounded-lg border border-gray-100 bg-white p-3 dark:border-gray-800 dark:bg-gray-900">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($images as $i => $media)
                <div @if ($i >= 4) x-show="expanded" @endif class="aspect-[4/3] overflow-hidden rounded-lg">
                    @if ($media->media_type === 'video')
                        <video
                            src="{{ asset('storage/'.$media->image_path) }}"
                            class="h-full w-full object-cover"
                            controls
                            preload="metadata"
                        ></video>
                    @else
                        <img
                            src="{{ asset('storage/'.$media->image_path) }}"
                            alt="{{ $heading }}"
                            class="h-full w-full object-cover transition hover:scale-105"
                        />
                    @endif
                </div>
            @endforeach
        </div>

        @if (count($images) > 4)
            <div class="mt-3 flex justify-center">
                <button
                    type="button"
                    @click="expanded = !expanded"
                    class="inline-flex items-center gap-1.5 rounded-full border border-primary-200 bg-white px-4 py-1.5 text-xs font-medium text-primary-600 dark:border-primary-800 dark:bg-gray-900 dark:text-primary-400"
                >
                    <span x-text="expanded ? @js(__('admin.show_less_photos')) : @js(__('admin.show_more_photos'))"></span>
                </button>
            </div>
        @endif
    </div>
</div>
