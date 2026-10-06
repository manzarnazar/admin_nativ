{{--
    Shared "Media" tab body. Expects:
    - $primaryImages: Collection<int, PropertyImage> — the mandatory
      showcase photo(s)/video set during the property creation wizard.
    - $galleryGroups: array<int, array{name: string, count: int, images: Collection<int, PropertyImage>}>
    - $mediaCounts: array{all: int, photos: int, videos: int}

    Groups mirror PropertyImage.group_name (e.g. "Bedroom", "Outdoors"); each
    image renders as a photo or a controllable <video> based on its own
    media_type. Groups beyond 4 items reveal the rest via Alpine, no
    Livewire round-trip needed since all images are already loaded.
--}}
<div class="rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.property_media') }}</h3>

        <select
            wire:model.live="mediaFilter"
            class="rounded-lg border-gray-300 text-sm font-medium text-gray-700 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
        >
            <option value="all">{{ __('admin.all_media') }} ({{ $mediaCounts['all'] }})</option>
            <option value="photos">{{ __('admin.photos') }} ({{ $mediaCounts['photos'] }})</option>
            <option value="videos">{{ __('admin.videos') }} ({{ $mediaCounts['videos'] }})</option>
        </select>
    </div>

    @if ($primaryImages->isNotEmpty())
        @include('filament.schemas.components.property-detail.media-group', [
            'heading' => __('admin.primary_showcase_media'),
            'count' => $primaryImages->count(),
            'images' => $primaryImages,
        ])
    @endif

    @foreach ($galleryGroups as $group)
        @include('filament.schemas.components.property-detail.media-group', [
            'heading' => $group['name'],
            'count' => $group['count'],
            'images' => $group['images'],
        ])
    @endforeach

    @if ($primaryImages->isEmpty() && empty($galleryGroups))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.no_media_uploaded') }}</p>
    @endif
</div>
</div>
