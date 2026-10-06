@php
    $documents = $this->getDocuments();
    $docCount = count($documents);
@endphp

<div class="space-y-6">
    <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ __('admin.documentation') }}</h3>

    {{-- Summary Cards --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
        <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900">
                <x-heroicon-o-document-text class="h-5 w-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.documents_uploaded') }}</p>
                <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $docCount }}</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900">
                <x-heroicon-o-check-badge class="h-5 w-5 text-green-600 dark:text-green-400" />
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.property_verified_on') }}</p>
                <p class="text-lg font-bold text-gray-950 dark:text-white">—</p>
            </div>
        </div>
        <div class="flex items-center gap-3 rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-orange-100 dark:bg-orange-900">
                <x-heroicon-o-user-circle class="h-5 w-5 text-orange-600 dark:text-orange-400" />
            </div>
            <div>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.verified_by') }}</p>
                <p class="text-lg font-bold text-gray-950 dark:text-white">—</p>
            </div>
        </div>
    </div>

    {{-- Documents Table --}}
    @if ($docCount > 0)
        <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-5 py-3 font-medium text-gray-500 dark:text-gray-400">{{ __('admin.document') }}</th>
                        <th class="px-5 py-3 font-medium text-gray-500 dark:text-gray-400">{{ __('admin.uploaded_on') }}</th>
                        <th class="px-5 py-3 font-medium text-gray-500 dark:text-gray-400">{{ __('admin.status') }}</th>
                        <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">{{ __('admin.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($documents as $doc)
                        <tr>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-red-50 dark:bg-red-950">
                                        <x-heroicon-o-document class="h-5 w-5 text-red-500" />
                                    </div>
                                    <span class="font-medium text-gray-950 dark:text-white">{{ $doc['name'] }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-gray-500 dark:text-gray-400">
                                {{ $doc['uploaded_at']?->format('d M, Y') ?? '-' }}
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                    {{ __('admin.uploaded') }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                @if ($doc['path'])
                                    <div class="flex items-center justify-end gap-2">
                                        <a
                                            href="{{ asset('storage/' . $doc['path']) }}"
                                            target="_blank"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
                                        >
                                            <x-phosphor-eye class="h-4 w-4" />
                                        </a>
                                        <a
                                            href="{{ asset('storage/' . $doc['path']) }}"
                                            download
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 transition hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-800"
                                        >
                                            <x-heroicon-o-arrow-down-tray class="h-4 w-4" />
                                        </a>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('admin.no_documents_uploaded') }}</p>
    @endif
</div>
