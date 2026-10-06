@php
    $blog = $this->getBlog();
@endphp

<x-filament-panels::page>
    <style>
        .blog-view-page-wrapper {
            margin: 0;
        }
        

        .blog-view-content {
            background-color: #F7F7F7;
            padding: 0px 16px 32px;
            min-height: calc(100vh - 150px);
        }
        @media (min-width: 768px) {
            .blog-view-content {
                padding: 0px 24px 32px;
            }
        }
        @media (min-width: 1024px) {
            .blog-view-content {
                padding: 0px 32px 32px;
            }
        }
        .dark .blog-view-content {
            background-color: rgb(11 17 26);
        }

        .master-detail-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }
        @media (min-width: 1024px) {
            .master-detail-grid { grid-template-columns: 2.2fr 1fr; }
        }
        
        /* Custom meta card styling to prevent tailwind v4 compilation issues */
        .blog-meta-card {
            background-color: #FFFFFF;
            border: 1px solid #EDEDED;
            border-radius: 16px;
            padding: 24px;
            display: flex;
            align-items: center;
            gap: 40px;
        }
        .dark .blog-meta-card {
            background-color: rgb(31 41 55);
            border-color: rgb(55 65 81);
        }
        .blog-meta-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .blog-meta-label {
            color: #777777;
            font-size: 14px;
            font-weight: 400;
            font-family: 'Outfit', sans-serif;
            line-height: 20px;
        }
        .dark .blog-meta-label {
            color: #9ca3af;
        }
        .blog-meta-value {
            color: #0f172a;
            font-size: 16px;
            font-weight: 600;
            font-family: 'Outfit', sans-serif;
            line-height: 24px;
        }
        .dark .blog-meta-value {
            color: #ffffff;
        }
        .blog-meta-divider {
            width: 1px;
            height: 48px;
            background-color: #EDEDED;
        }
        .dark .blog-meta-divider {
            background-color: #4b5563;
        }

        /* Main blog content card */
        .blog-main-card {
            background-color: #FFFFFF;
            border: 1px solid #EDEDED;
            border-radius: 16px;
            padding: 24px;
        }
        .dark .blog-main-card {
            background-color: rgb(31 41 55);
            border-color: rgb(55 65 81);
        }


        /* Sticky Preview Sidebar */
        .blog-preview-sidebar {
            position: sticky;
            top: 0;
            align-self: start;
        }

        /* Figma dev tokens mapping */
        .bg-Colors-Shades-Neutral-N---950 {
            background-color: #0c0a09 !important;
        }
        .text-Colors-Shades-Neutral-N---50 {
            color: #f5f5f4 !important;
        }
        .bg-Colors-Shades-Neutral-N---50 {
            background-color: #FFFFFF !important;
        }
        .dark .bg-Colors-Shades-Neutral-N---950 {
            background-color: #f5f5f4 !important;
        }
        .dark .text-Colors-Shades-Neutral-N---50 {
            color: #0c0a09 !important;
        }
        .dark .bg-Colors-Shades-Neutral-N---50 {
            background-color: #0c0a09 !important;
        }
    </style>

    <div class="blog-view-page-wrapper">

        {{-- Content Area with Grey background --}}
        <div class="blog-view-content">
            <div class="master-detail-grid">
                {{-- LEFT COLUMN (2/3) --}}
                <div class="space-y-6">
                    {{-- Meta Info Card --}}
                    <div class="blog-meta-card">
                        <div class="blog-meta-item">
                            <div class="blog-meta-label">Category</div>
                            <div class="blog-meta-value">
                                {{ $blog->category?->name ?? 'Uncategorized' }}
                            </div>
                        </div>
                        
                        <div class="blog-meta-divider"></div>
                        
                        <div class="blog-meta-item">
                            <div class="blog-meta-label">Published Date</div>
                            <div class="blog-meta-value">
                                {{ $blog->published_at ? $blog->published_at->format('M d, Y') : $blog->created_at->format('M d, Y') }}
                            </div>
                        </div>
                        
                        <div class="blog-meta-divider"></div>
                        
                        <div class="blog-meta-item">
                            <div class="blog-meta-label">Read Time</div>
                            <div class="blog-meta-value">
                                {{ $blog->read_time_minutes ? $blog->read_time_minutes . ' Minutes' : '—' }}
                            </div>
                        </div>
                    </div>

                    {{-- Main Content Card --}}
                    <div class="blog-main-card">
                        {{-- Cover Image (restricted height) --}}
                        @if ($blog->cover_image)
                            <div class="overflow-hidden rounded-2xl mb-6">
                                <img
                                    src="{{ Storage::disk('public')->url($blog->cover_image) }}"
                                    alt="{{ $blog->title }}"
                                    class="h-[450px] w-full object-cover rounded-2xl"
                                />
                            </div>
                        @endif

                        {{-- Content Section --}}
                        <div class="prose max-w-none dark:prose-invert font-['Outfit'] text-gray-800 dark:text-gray-300">
                            {!! $blog->content !!}
                        </div>
                    </div>
                </div>

                {{-- RIGHT COLUMN (1/3) --}}
                <div class="blog-preview-sidebar space-y-4">
                    {{-- Preview Card --}}
                    <div class="self-stretch p-5 bg-Colors-Shades-Neutral-N---50 rounded-2xl outline outline-1 outline-offset-[-1px] outline-Colors-Shades-Neutral-N---200 inline-flex flex-col justify-start items-start gap-4 font-sans">
                        <div class="self-stretch inline-flex justify-center items-center gap-4">
                            <div class="flex-1 text-center justify-start text-black dark:text-white text-base font-semibold leading-6">Live Website Preview</div>
                        </div>
                        <div class="self-stretch h-48 min-w-80 p-3 relative bg-Colors-Shades-Theme-P---50 rounded-2xl flex flex-col justify-end items-end overflow-hidden">
                            @if ($blog->cover_image)
                                <img
                                    src="{{ Storage::disk('public')->url($blog->cover_image) }}"
                                    alt="{{ $blog->title }}"
                                    class="absolute inset-0 h-full w-full object-cover rounded-2xl"
                                />
                            @else
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="text-Colors-Shades-Theme-P---700 text-xs font-semibold leading-4">1620X823</div>
                                </div>
                            @endif
                            <div class="relative z-10 p-2 bg-Colors-Shades-Neutral-N---50 rounded-lg outline outline-1 outline-offset-[-1px] outline-Colors-Shades-Neutral-N---200 flex flex-col justify-center items-center gap-0.5">
                                <div class="justify-start text-Colors-Shades-Neutral-N---950 text-lg font-semibold leading-6 dark:text-white">
                                    {{ $blog->published_at ? $blog->published_at->format('d') : $blog->created_at->format('d') }}
                                </div>
                                <div class="justify-start text-Colors-Shades-Neutral-N---700 text-xs font-medium leading-4 dark:text-gray-400">
                                    {{ $blog->published_at ? $blog->published_at->format('M') : $blog->created_at->format('M') }}
                                </div>
                            </div>
                        </div>
                        <div class="self-stretch min-w-80 flex flex-col justify-center items-start gap-6">
                            <div class="self-stretch flex flex-col justify-start items-start gap-4">
                                <div class="size- px-2 py-0.5 bg-Colors-Shades-Theme-P---50 rounded-full inline-flex justify-center items-center gap-2">
                                    <div class="text-center justify-start text-Colors-Primary text-xs font-semibold leading-4">
                                        {{ $blog->category?->name ?? (__('admin.category') ?? 'Category') }}
                                    </div>
                                </div>
                                <div class="self-stretch flex flex-col justify-start items-start gap-2">
                                    <div class="self-stretch justify-start text-Colors-Shades-Neutral-N---950 text-lg font-semibold leading-6 line-clamp-1 dark:text-white">
                                        {{ $blog->title ?: 'Blog Title Goes Here' }}
                                    </div>
                                    <div class="self-stretch justify-start text-neutral-700 text-sm font-normal leading-5 line-clamp-2 dark:text-gray-300">
                                        {{ $blog->short_description ?: 'A short summary of your blog post will appear here. Write something catchy to engage your readers...' }}
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
            </div>
        </div>
    </div>
</x-filament-panels::page>
