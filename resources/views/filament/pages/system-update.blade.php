<x-filament-panels::page>
    {{-- Version Badge --}}
    <div class="mb-6 flex items-center gap-3 bg-white px-4 py-2.5 rounded-xl border border-gray-200 shadow-xs dark:bg-gray-800 dark:border-gray-700 w-fit">
        <div class="flex items-center gap-2">
            <span class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-success-400 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-success-500"></span>
            </span>
            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('admin.current_version') }}</span>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold"
              style="background-color: #d1fae5; color: #065f46;">
            <svg class="h-3.5 w-3.5" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
            </svg>
            v{{ $currentVersion }}
        </span>
    </div>

    <form wire:submit.prevent="mountAction('confirmUpdate')" enctype="multipart/form-data">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            {{-- Left column: main action flow — Purchase Code, Update File, combined
                 warning, Submit, in that order, concluding the update journey. --}}
            <div class="lg:col-span-2">
                {{-- Purchase Code --}}
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 mb-5">
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-heroicon-o-key class="h-5 w-5 text-primary-500" />
                            {{ __('admin.purchase_code') }}
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.purchase_code_helper') }}</p>
                    </div>
                    <div>
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="purchaseCode"
                            id="purchase_code"
                            placeholder="{{ __('admin.purchase_code_placeholder') }}"
                            class="system-update-brand-input block w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder:text-gray-400"
                        />
                        @error('purchaseCode')
                            <p class="mt-1.5 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- File Upload --}}
                <div
                    x-data="{ isUploading: false, progress: 0 }"
                    x-on:livewire-upload-start="isUploading = true"
                    x-on:livewire-upload-finish="isUploading = false; progress = 0"
                    x-on:livewire-upload-error="isUploading = false"
                    x-on:livewire-upload-progress="progress = $event.detail.progress"
                    class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 mb-5"
                >
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <x-heroicon-o-archive-box-arrow-down class="h-5 w-5 text-primary-500" />
                            {{ __('admin.update_file') }}
                            <span class="text-xs font-normal text-danger-500 ml-1">({{ __('admin.only_zip_allowed') }})</span>
                        </h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.update_file_helper') }}</p>
                    </div>
                    <div>
                        <label
                            class="flex w-full cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 py-8 transition hover:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-600"
                            :class="{ 'pointer-events-none opacity-70': isUploading }"
                            for="update_file_input"
                        >
                            {{-- Uploading Progress State --}}
                            <div x-show="isUploading" x-cloak class="w-full px-6 flex flex-col items-center justify-center">
                                <div class="flex items-center gap-2 mb-3">
                                    <svg class="animate-spin h-5 w-5 text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ __('admin.uploading') }} <span x-text="progress + '%'"></span>
                                    </span>
                                </div>
                                <div class="w-full max-w-xs bg-gray-200 rounded-full h-2 dark:bg-gray-700 overflow-hidden">
                                    <div class="bg-primary-600 h-2 rounded-full transition-all duration-150" :style="'width: ' + progress + '%'"></div>
                                </div>
                            </div>

                            {{-- Normal / File Selected State --}}
                            <div x-show="!isUploading" class="flex flex-col items-center justify-center">
                                @if ($updateFile)
                                    @php
                                        try {
                                            $fileSize = number_format($updateFile->getSize() / 1024, 1) . ' KB';
                                        } catch (\Throwable $e) {
                                            $fileSize = null;
                                        }
                                    @endphp
                                    <x-heroicon-o-document-check class="mb-2 h-8 w-8 text-success-500" />
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $updateFile->getClientOriginalName() }}</p>
                                    @if ($fileSize)
                                        <p class="mt-1 text-xs text-gray-400">{{ $fileSize }}</p>
                                    @endif
                                @else
                                    <x-heroicon-o-cloud-arrow-up class="mb-2 h-8 w-8 text-gray-400" />
                                    <p class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.click_to_upload') }}</p>
                                    <p class="mt-1 text-xs text-gray-400">.zip {{ __('admin.files_only') }}</p>
                                @endif
                            </div>
                        </label>
                        <input
                            type="file"
                            id="update_file_input"
                            wire:model="updateFile"
                            accept=".zip"
                            class="sr-only"
                        />
                        @error('updateFile')
                            <p class="mt-1.5 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Before You Update: backup + sequential-update notes, combined into
                     one box instead of two stacked colored banners. --}}
                <div class="mb-5 flex gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <x-heroicon-o-information-circle class="mt-0.5 h-5 w-5 flex-shrink-0 text-gray-400 dark:text-gray-500" />
                    <div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">
                        <p>
                            <span class="font-semibold text-danger-600 dark:text-danger-400">{{ __('admin.important_label') ?? 'Important' }}:</span>
                            {{ __('admin.backup_notice_text') }}
                        </p>
                        <p>
                            <span class="font-semibold text-gray-700 dark:text-gray-200">{{ __('admin.note') }}:</span>
                            {{ __('admin.sequential_update_note') }}
                        </p>
                    </div>
                </div>

                {{-- Submit --}}
                <div class="flex items-center gap-3">
                    {{ $this->confirmUpdateAction }}
                </div>
            </div>

            {{-- Right column: maintenance & utilities only — no submit button,
                 no critical warnings here. --}}
            <div class="lg:col-span-1">
                {{-- System Refresh --}}
                <div class="relative overflow-hidden rounded-2xl border border-gray-200/50 bg-white p-[1px] shadow-sm transition-all duration-300 hover:shadow-md dark:border-gray-700/50 dark:bg-gray-800">
                    {{-- Decorative background glow --}}
                    <div class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-primary-500/10 blur-3xl dark:bg-primary-500/20"></div>
                    <div class="pointer-events-none absolute -bottom-10 -left-10 h-32 w-32 rounded-full bg-success-500/10 blur-3xl dark:bg-success-500/20"></div>

                    <div class="relative h-full rounded-2xl bg-white p-6 dark:bg-gray-800/90 backdrop-blur-xl">
                        <div class="mb-4 flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-600 ring-1 ring-inset ring-primary-500/20 dark:bg-primary-900/30 dark:text-primary-400 dark:ring-primary-500/30">
                                <x-heroicon-o-arrow-path class="h-5 w-5" />
                            </div>
                            <h3 class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">
                                {{ __('admin.system_refresh') }}
                            </h3>
                        </div>

                        <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 leading-relaxed">
                            {{ __('admin.system_refresh_desc') }}
                        </p>

                        <div class="flex flex-wrap gap-2 mb-8">
                            @foreach(['cache:clear', 'config:clear', 'route:clear', 'view:clear'] as $command)
                                <span class="inline-flex items-center rounded-lg bg-gray-100/80 px-2.5 py-1 text-[11px] font-mono font-semibold tracking-wide text-gray-600 transition-colors hover:bg-gray-200 dark:bg-gray-700/50 dark:text-gray-300 dark:hover:bg-gray-600">
                                    {{ $command }}
                                </span>
                            @endforeach
                        </div>

                        <div class="w-full">
                            <x-filament::button
                                type="button"
                                color="primary"
                                icon="heroicon-m-arrow-path"
                                class="w-full justify-center shadow-md shadow-primary-500/20 transition-all hover:shadow-lg hover:shadow-primary-500/30"
                                wire:click="clearCache"
                                wire:loading.attr="disabled"
                                wire:target="clearCache"
                            >
                                <span wire:loading.remove wire:target="clearCache">{{ __('admin.clear_system_cache') }}</span>
                                <span wire:loading wire:target="clearCache">{{ __('admin.optimizing') }}</span>
                            </x-filament::button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- After a successful update, hit the standalone recovery script
         (never boots Laravel, so it works even if the update left a stale
         provider reference behind), then reload. The delay before reloading
         gives the success notification above time to actually be seen —
         reloading immediately would wipe it off screen before it renders. --}}
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('system-update-applied', () => {
                fetch('{{ url('/clear-update-cache.php') }}').finally(() => {
                    setTimeout(() => window.location.reload(), 3000);
                });
            });
        });
    </script>
</x-filament-panels::page>
