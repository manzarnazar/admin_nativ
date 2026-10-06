@php
    $title = $get('title');
    $shortDescription = $get('short_description');
    $categoryId = $get('blog_category_id');
    $coverImage = $get('cover_image');

    $categoryName = $categoryId ? \App\Models\BlogCategory::find($categoryId)?->name : null;

    // Resolve existing stored image URL (edit mode)
    $storedImageUrl = null;
    if ($coverImage) {
        if (is_string($coverImage) && Storage::disk('public')->exists($coverImage)) {
            $storedImageUrl = Storage::disk('public')->url($coverImage);
        } elseif (is_array($coverImage)) {
            $firstFile = collect($coverImage)->first();
            if ($firstFile && is_string($firstFile) && Storage::disk('public')->exists($firstFile)) {
                $storedImageUrl = Storage::disk('public')->url($firstFile);
            }
        }
    }
@endphp

<div
    x-data="{ previewUrl: @js($storedImageUrl) }"
    x-on:file-pond-add-file.window="
        const file = $event.detail?.file;
        if (file && file.type?.startsWith('image/')) {
            previewUrl = URL.createObjectURL(file);
        }
    "
    x-on:file-pond-remove-file.window="previewUrl = @js($storedImageUrl)"
    x-init="
        // Listen for FilePond native events at document level
        document.addEventListener('FilePond:addfile', (e) => {
            const file = e.detail?.file?.file;
            if (file && file.type?.startsWith('image/')) {
                previewUrl = URL.createObjectURL(file);
            }
        });
        document.addEventListener('FilePond:removefile', () => {
            previewUrl = @js($storedImageUrl);
        });
    "
    class="fi-blog-preview-sticky space-y-4"
>
    {{-- Preview Card --}}
    <div class="self-stretch p-5 bg-Colors-Shades-Neutral-N---50 rounded-2xl outline outline-1 outline-offset-[-1px] outline-Colors-Shades-Neutral-N---200 inline-flex flex-col justify-start items-start gap-4 font-sans">
        <div class="self-stretch inline-flex justify-center items-center gap-4">
            <div class="flex-1 text-center justify-start text-black dark:text-white text-base font-semibold leading-6">
                {{ __('admin.live_website_preview') ?? 'Live Website Preview' }}
            </div>
        </div>
        <div class="self-stretch h-48 min-w-80 p-3 relative bg-Colors-Shades-Theme-P---50 rounded-2xl flex flex-col justify-end items-end overflow-hidden">
            <template x-if="previewUrl">
                <img
                    :src="previewUrl"
                    alt="Cover preview"
                    class="absolute inset-0 h-full w-full object-cover rounded-2xl"
                />
            </template>
            <template x-if="! previewUrl">
                <div class="absolute inset-0 flex items-center justify-center">
                    <div class="text-Colors-Shades-Theme-P---700 text-xs font-semibold leading-4">1620X823</div>
                </div>
            </template>
            <div class="relative z-10 p-2 bg-Colors-Shades-Neutral-N---50 rounded-lg outline outline-1 outline-offset-[-1px] outline-Colors-Shades-Neutral-N---200 flex flex-col justify-center items-center gap-0.5">
                <div class="justify-start text-Colors-Shades-Neutral-N---950 text-lg font-semibold leading-6 dark:text-white">{{ now()->format('d') }}</div>
                <div class="justify-start text-Colors-Shades-Neutral-N---700 text-xs font-medium leading-4 dark:text-gray-400">{{ now()->format('M') }}</div>
            </div>
        </div>
        <div class="self-stretch min-w-80 flex flex-col justify-center items-start gap-6">
            <div class="self-stretch flex flex-col justify-start items-start gap-4">
                <div class="size- px-2 py-0.5 bg-Colors-Shades-Theme-P---50 rounded-full inline-flex justify-center items-center gap-2">
                    <div class="text-center justify-start text-Colors-Primary text-xs font-semibold leading-4">
                        {{ $categoryName ?: (__('admin.category') ?? 'Category') }}
                    </div>
                </div>
                <div class="self-stretch flex flex-col justify-start items-start gap-2">
                    <div class="self-stretch justify-start text-Colors-Shades-Neutral-N---950 text-lg font-semibold leading-6 line-clamp-1 dark:text-white">
                        {{ $title ?: 'Blog Title Goes Here' }}
                    </div>
                    <div class="self-stretch justify-start text-neutral-700 text-sm font-normal leading-5 line-clamp-2 dark:text-gray-300">
                        {{ $shortDescription ?: 'A short summary of your blog post will appear here. Write something catchy to engage your readers...' }}
                    </div>
                </div>
            </div>
            <div data-icon-left="false" data-icon-right="true" data-only-icon="OFF" data-size="btn-md" data-status="Default" data-type="Secondary" class="size- px-4 py-2 bg-Colors-Shades-Neutral-N---950 rounded-full inline-flex justify-center items-center gap-2">
                <div class="justify-start text-Colors-Shades-Neutral-N---50 text-base font-normal leading-6">Read More</div>
                <div class="size-6 relative overflow-hidden flex items-center justify-center">
                    <svg class="h-3.5 w-3.5 text-Colors-Shades-Neutral-N---50" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="color: #f5f5f4 !important;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Info Notice Box --}}
    <div class="self-stretch p-6 bg-Colors-Shades-Neutral-N---200 rounded-2xl outline outline-1 outline-offset-[-1px] outline-Colors-Shades-Neutral-N---200 flex flex-col justify-start items-start gap-6">
        <div class="w-full text-center justify-center text-Colors-Shades-Neutral-N---700 text-sm font-medium font-sans leading-5">
            {{ __('admin.preview_shows_how_the_card_appears_on_the_live_website') ?? 'Preview shows how the card appears on the live website.' }}
        </div>
    </div>
</div>
