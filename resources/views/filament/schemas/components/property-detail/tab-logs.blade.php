{{--
    Shared "Logs" tab body. Expects:
    - $property — used only for resolvedTimezone(), so timestamps display in
      the property's own local time rather than the server/app timezone.
    - $auditLogs: Collection<int, \Spatie\Activitylog\Models\Activity>
    - $auditLogDate: ?string

    Ported from the audit_logs tab on app/Filament/Pages/AllPartnersDetail.php
    (same Spatie activitylog query, same causer resolution, same list
    styling), just scoped to Property::class instead of Partner::class —
    Property already logs activity via LogsActivity::logAll(), no new
    logging needed.
--}}
@php
    $FILE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'mp4', 'mov', 'webm', 'doc', 'docx'];
    $IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    $isFilePath = function (mixed $value) use ($FILE_EXTENSIONS): bool {
        if (! is_string($value) || empty($value) || $value === '—') {
            return false;
        }
        $parts = explode('|', $value);
        foreach ($parts as $p) {
            $ext = strtolower(pathinfo(trim($p), PATHINFO_EXTENSION));
            if (in_array($ext, $FILE_EXTENSIONS)) {
                return true;
            }
        }
        return false;
    };

    $renderFileCard = function (mixed $value) use ($IMAGE_EXTENSIONS): string {
        if ($value === null || $value === '' || $value === '—') {
            return '<span class="flex h-24 w-24 items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50 text-xs text-gray-400 dark:border-gray-600 dark:bg-gray-800">—</span>';
        }
        $paths = array_values(array_filter(array_map('trim', explode('|', (string) $value))));
        if (empty($paths)) {
            return '<span class="flex h-24 w-24 items-center justify-center rounded-xl border border-dashed border-gray-200 bg-gray-50 text-xs text-gray-400 dark:border-gray-600 dark:bg-gray-800">—</span>';
        }
        $cards = array_map(function (string $p) use ($IMAGE_EXTENSIONS): string {
            $isUrl = filter_var($p, FILTER_VALIDATE_URL);
            $url = e($isUrl ? $p : asset('storage/' . $p));
            $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
            $isImage = in_array($ext, $IMAGE_EXTENSIONS);
            $isPdf = $ext === 'pdf';

            $existsOnDisk = $isUrl || \Illuminate\Support\Facades\Storage::disk('public')->exists($p);

            if (! $existsOnDisk) {
                return '<div class="flex h-24 w-24 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-gray-300 bg-gray-50/80 p-2 text-center text-gray-400 dark:border-gray-700 dark:bg-gray-800/60" title="File was removed or deleted from server">'
                    . '<svg class="h-6 w-6 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>'
                    . '<span class="text-[10px] font-medium text-gray-400">File Deleted</span>'
                    . '</div>';
            }

            $fallbackHtml = e('<div class="flex h-full w-full flex-col items-center justify-center gap-1 bg-gray-50 p-1 text-center text-gray-400 dark:bg-gray-800"><svg class="h-6 w-6 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg><span class="text-[10px]">File Deleted</span></div>');

            if ($isImage) {
                return '<button type="button" @click="$dispatch(\'open-property-doc\', { url: \'' . $url . '\', type: \'image\' })"'
                    . ' class="block h-24 w-24 shrink-0 overflow-hidden rounded-xl border border-gray-200 bg-gray-50 shadow-sm transition hover:opacity-80 dark:border-gray-700 dark:bg-gray-800" title="Click to view image">'
                    . '<img src="' . $url . '" class="h-full w-full object-cover" alt="Image Preview" onerror="this.onerror=null; this.parentElement.innerHTML=\'' . $fallbackHtml . '\';" />'
                    . '</button>';
            }

            if ($isPdf) {
                return '<button type="button" @click="$dispatch(\'open-property-doc\', { url: \'' . $url . '\', type: \'pdf\' })"'
                    . ' class="flex h-24 w-24 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-red-200 bg-red-50/80 p-2 text-red-600 shadow-sm transition hover:bg-red-100 dark:border-red-900/40 dark:bg-red-950/40 dark:text-red-400" title="Click to view PDF">'
                    . '<svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>'
                    . '<span class="text-[11px] font-bold uppercase tracking-wider">PDF</span>'
                    . '</button>';
            }

            $upperExt = strtoupper($ext ?: 'DOC');
            return '<button type="button" @click="$dispatch(\'open-property-doc\', { url: \'' . $url . '\', type: \'other\' })"'
                . ' class="flex h-24 w-24 shrink-0 flex-col items-center justify-center gap-1 rounded-xl border border-blue-200 bg-blue-50/80 p-2 text-blue-600 shadow-sm transition hover:bg-blue-100 dark:border-blue-900/40 dark:bg-blue-950/40 dark:text-blue-400" title="Click to view document">'
                . '<svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>'
                . '<span class="text-[10px] font-bold uppercase tracking-wider">' . e($upperExt) . '</span>'
                . '</button>';
        }, $paths);

        return '<div class="flex items-center gap-2 flex-wrap">' . implode('', $cards) . '</div>';
    };

    $renderValue = function (mixed $value) use ($isFilePath, $renderFileCard): string {
        if ($value === null || $value === '') {
            return '<span class="text-gray-400">—</span>';
        }
        if ($value === true) {
            return e(__('admin.yes'));
        }
        if ($value === false) {
            return e(__('admin.no'));
        }
        $str = (string) $value;
        if ($isFilePath($str)) {
            return $renderFileCard($str);
        }
        return e($str);
    };
@endphp

{{-- Document preview modal --}}
<div
    x-data="{ show: false, url: '', type: 'image' }"
    @open-property-doc.window="show = true; url = $event.detail.url; type = $event.detail.type"
    x-show="show"
    x-on:keydown.escape.window="show = false"
    @click.self="show = false"
    class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/75 backdrop-blur-sm p-4"
    style="display:none"
>
    <div class="relative max-h-[90vh] w-full max-w-4xl overflow-auto rounded-2xl bg-white shadow-2xl dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.document_preview') }}</h3>
            <button
                type="button"
                @click="show = false"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800 dark:hover:text-gray-300"
            >
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>
        <div class="flex items-center justify-center p-6">
            <template x-if="type === 'image'">
                <img :src="url" class="max-h-[70vh] max-w-full rounded-lg object-contain" alt="Preview" />
            </template>
            <template x-if="type === 'pdf'">
                <iframe :src="url" class="h-[70vh] w-full rounded-lg border border-gray-200 dark:border-gray-700" title="PDF Preview"></iframe>
            </template>
            <template x-if="type === 'other'">
                <div class="flex flex-col items-center gap-4 text-center">
                    <x-heroicon-o-document class="h-16 w-16 text-gray-300 dark:text-gray-600" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.download_file') }}</p>
                    <a
                        :href="url"
                        target="_blank"
                        class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700"
                    >
                        {{ __('admin.view') }}
                    </a>
                </div>
            </template>
        </div>
    </div>
</div>

<div class="rounded-2xl border border-[#EDEDED] bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.audit_logs') }}</h3>

        <div class="flex items-center gap-2">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.filter') }}:</span>
            <input
                type="date"
                wire:change="setAuditLogDate($event.target.value)"
                value="{{ $auditLogDate ?? '' }}"
                class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
            />
        </div>
    </div>

    @if ($auditLogs->isEmpty())
        <div class="p-12 text-center">
            <x-heroicon-o-clipboard-document-list class="mx-auto mb-3 h-10 w-10 text-gray-300 dark:text-gray-600" />
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.no_audit_logs') }}</p>
        </div>
    @else
        <ul class="flex flex-col gap-4">
            @foreach ($auditLogs as $log)
                @php
                    $changes = $log->properties['attributes'] ?? [];
                    $oldValues = $log->properties['old'] ?? [];
                    $isEmptyLogValue = fn (mixed $val): bool => $val === null || $val === '';
                    $changes = array_filter(
                        $changes,
                        fn (mixed $newValue, string $field): bool => ! ($isEmptyLogValue($oldValues[$field] ?? null) && $isEmptyLogValue($newValue)),
                        ARRAY_FILTER_USE_BOTH
                    );
                @endphp
                <li class="rounded-xl bg-[#F7F7F7] p-4 sm:px-5 dark:bg-gray-700/50" x-data="{ open: false }">
                    <div
                        @if (! empty($changes)) @click="open = !open" class="flex cursor-pointer items-center gap-4" @else class="flex items-center gap-4" @endif
                    >
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full border border-blue-100 bg-white shadow-sm dark:border-blue-900/50 dark:bg-gray-800">
                            {!! svg('others.PencilSimpleLine', 'h-4 w-4 text-blue-500 dark:text-blue-400')->toHtml() !!}
                        </div>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $log->description }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ __('admin.by') }} {{ $log->causer?->name ?? __('admin.system') }} - {{ $log->created_at->clone()->setTimezone($property->resolvedTimezone())->format('Y-m-d h:i A') }}
                            </p>
                        </div>

                        @if (! empty($changes))
                            <x-heroicon-o-chevron-down
                                class="h-5 w-5 shrink-0 text-gray-400 transition-transform duration-200"
                                x-bind:class="{ 'rotate-180': open }"
                            />
                        @endif
                    </div>

                    @if (! empty($changes))
                        <div x-show="open" x-collapse class="mt-3 ml-14 rounded-xl border border-gray-200/80 bg-white p-3.5 dark:border-gray-700/80 dark:bg-gray-800">
                            <div class="space-y-3">
                                @foreach ($changes as $field => $newValue)
                                    @php
                                        $oldValue = $log->properties['old'][$field] ?? null;
                                        $isFile = $isFilePath($oldValue) || $isFilePath($newValue);
                                    @endphp
                                    @if ($isFile)
                                        <div class="rounded-xl border border-gray-100 bg-[#FAFAFA] p-3.5 dark:border-gray-700/60 dark:bg-gray-800/60">
                                            <div class="mb-2.5 flex items-center gap-2">
                                                <span class="inline-flex h-2 w-2 rounded-full bg-primary-500"></span>
                                                <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200">{{ \Illuminate\Support\Str::headline($field) }}</span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                {!! $renderFileCard($oldValue) !!}
                                                <x-heroicon-o-arrow-right class="h-4 w-4 shrink-0 text-gray-400" />
                                                {!! $renderFileCard($newValue) !!}
                                            </div>
                                        </div>
                                    @else
                                        <div class="flex flex-col gap-2 rounded-xl border border-gray-100 bg-[#FAFAFA] p-3.5 sm:flex-row sm:items-center sm:justify-between dark:border-gray-700/60 dark:bg-gray-800/60">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex h-2 w-2 rounded-full bg-gray-400"></span>
                                                <span class="text-xs font-bold uppercase tracking-wider text-gray-700 dark:text-gray-200">{{ \Illuminate\Support\Str::headline($field) }}</span>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                                <span class="inline-flex items-center rounded-lg bg-gray-100 px-3 py-1.5 font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                                                    {!! $renderValue($oldValue) !!}
                                                </span>
                                                <x-heroicon-o-arrow-right class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                                                <span class="inline-flex items-center rounded-lg border border-primary-200/80 bg-primary-50 px-3 py-1.5 font-semibold text-primary-700 dark:border-primary-800 dark:bg-primary-900/30 dark:text-primary-300">
                                                    {!! $renderValue($newValue) !!}
                                                </span>
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
