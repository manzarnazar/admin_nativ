<x-filament-panels::page>
    {{-- Tab Switcher --}}
    <div>
    <div class="inline-flex gap-1 rounded-xl p-1 dark:bg-gray-800" style="background-color: var(--brand-primary-light);">
        <button
            wire:click="switchTab('activity')"
            class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
            style="{{ $activeTab === 'activity' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
        >
            <x-heroicon-o-clipboard-document-list class="h-4 w-4" />
            {{ __('admin.activity_logs') }}
        </button>

        <button
            wire:click="switchTab('errors')"
            class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
            style="{{ $activeTab === 'errors' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
        >
            <x-heroicon-o-exclamation-triangle class="h-4 w-4" />
            {{ __('admin.error_logs') }}
        </button>
    </div>
    </div>

    {{-- Tab Content --}}
    @if ($activeTab === 'activity')
        {{ $this->table }}
    @else
        @php
            $errorLogs = $this->getErrorLogs();
        @endphp

        <div class="space-y-4">
            {{-- Clear Logs Button --}}
            @if (count($errorLogs) > 0)
                <div class="flex items-center justify-between">
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('admin.showing_latest_error_logs', ['count' => count($errorLogs)]) }}
                    </p>
                    <x-filament::button
                        color="danger"
                        size="sm"
                        icon="heroicon-o-trash"
                        wire:click="clearErrorLogs"
                        wire:confirm="{{ __('admin.clear_logs_confirmation') }}"
                    >
                        {{ __('admin.clear_logs') }}
                    </x-filament::button>
                </div>
            @endif

            @if (count($errorLogs) > 0)
                <div class="space-y-3">
                    @foreach ($errorLogs as $log)
                        <div x-data="{ expanded: false }" class="rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                            <button
                                @click="expanded = !expanded"
                                class="flex w-full items-start gap-3 p-4 text-left"
                            >
                                {{-- Level Badge --}}
                                <span @class([
                                    'mt-0.5 inline-flex shrink-0 items-center rounded-md px-2 py-1 text-xs font-medium',
                                    'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300' => $log['level'] === 'ERROR' || $log['level'] === 'CRITICAL',
                                    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300' => $log['level'] === 'WARNING',
                                    'bg-orange-100 text-orange-700 dark:bg-orange-900 dark:text-orange-300' => $log['level'] === 'ALERT' || $log['level'] === 'EMERGENCY',
                                ])>
                                    {{ $log['level'] }}
                                </span>

                                <div class="min-w-0 flex-1">
                                    {{-- Message Preview (single line, CSS-truncated) --}}
                                    <p class="truncate text-sm font-medium text-gray-950 dark:text-white">
                                        {{ $log['message'] }}
                                    </p>
                                    {{-- Datetime --}}
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $log['datetime'] }}
                                    </p>
                                </div>

                                {{-- Expand Icon --}}
                                @if (mb_strlen($log['message']) > 100 || $log['trace'])
                                    <x-heroicon-o-chevron-down class="mt-0.5 h-4 w-4 shrink-0 text-gray-400 transition" x-bind:class="expanded && 'rotate-180'" />
                                @endif
                            </button>

                            {{-- Full Message & Stack Trace --}}
                            @if (mb_strlen($log['message']) > 100 || $log['trace'])
                                <div x-show="expanded" x-collapse class="space-y-3 border-t border-gray-200 px-4 pb-4 pt-3 dark:border-gray-700">
                                    @if (mb_strlen($log['message']) > 100)
                                        <div>
                                            <p class="mb-1 text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">{{ __('admin.full_message') }}</p>
                                            <pre class="overflow-x-auto whitespace-pre-wrap rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ $log['message'] }}</pre>
                                        </div>
                                    @endif
                                    @if ($log['trace'])
                                        <div>
                                            <p class="mb-1 text-xs font-semibold uppercase text-gray-400 dark:text-gray-500">{{ __('admin.stack_trace') }}</p>
                                            <pre class="overflow-x-auto rounded-lg bg-gray-50 p-3 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ $log['trace'] }}</pre>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed border-gray-200 px-6 py-12 text-center dark:border-gray-700">
                    <x-heroicon-o-check-circle class="h-8 w-8 text-green-500" />
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ __('admin.no_error_logs') }}
                    </p>
                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        {{ __('admin.no_error_logs_description') }}
                    </p>
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
