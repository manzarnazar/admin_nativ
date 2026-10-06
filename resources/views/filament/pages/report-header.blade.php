<header class="flex items-start gap-3">
    {{-- Same icon-box treatment as the report cards on All Reports (bg-[#F7F7F7]/gray-800).
    Not .fi-header: that class carries a baked-in sm:justify-between that pushes this icon box
    and the title/description to opposite ends of the row instead of keeping them adjacent. --}}
    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg border-0 bg-[#F7F7F7] dark:bg-gray-800">
        <x-heroicon-o-information-circle class="h-6 w-6 text-gray-700 dark:text-gray-600" />
    </div>

    <div>
        <h1 class="text-xl font-bold text-gray-950 dark:text-white">{{ $title }}</h1>

        @if ($subheading)
        <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{{ $subheading }}</p>
        @endif
    </div>
</header>
