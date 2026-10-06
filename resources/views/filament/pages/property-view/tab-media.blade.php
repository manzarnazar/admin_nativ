@php
    $primaryImages = $property->primaryImages;
    $galleryGroups = $this->getGalleryGroups();
@endphp

<div class="space-y-6">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.property_media') }}</h3>

    {{-- Primary Showcase --}}
    @if ($primaryImages->isNotEmpty())
        <div>
            <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ __('admin.primary_showcase_media') }} ({{ $primaryImages->count() }})
            </h4>
            <div class="grid grid-cols-5 gap-3">
                @foreach ($primaryImages as $image)
                    <div class="aspect-[4/3] overflow-hidden rounded-lg">
                        @if ($image->media_type === 'video')
                            <video
                                src="{{ asset('storage/' . $image->image_path) }}"
                                class="h-full w-full object-cover"
                                controls
                                preload="metadata"
                            ></video>
                        @else
                            <img
                                src="{{ asset('storage/' . $image->image_path) }}"
                                alt="{{ __('admin.primary_image') }}"
                                class="h-full w-full object-cover transition hover:scale-105"
                            />
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Gallery Groups --}}
    @foreach ($galleryGroups as $group)
        <div>
            <h4 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">
                {{ $group['name'] }} ({{ $group['count'] }})
            </h4>
            <div class="grid grid-cols-5 gap-3">
                @foreach ($group['images'] as $imagePath)
                    <div class="aspect-[4/3] overflow-hidden rounded-lg">
                        <img
                            src="{{ asset('storage/' . $imagePath) }}"
                            alt="{{ $group['name'] }}"
                            class="h-full w-full object-cover transition hover:scale-105"
                        />
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach

    @if ($primaryImages->isEmpty() && empty($galleryGroups))
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.no_media_uploaded') }}</p>
    @endif
</div>
