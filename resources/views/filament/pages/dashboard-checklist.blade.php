<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">{{ __('admin.platform_setup_checklist') }}</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ __('admin.complete_these_essential_steps_to_fully_configure_your_platform') }}
                </p>
            </div>

            {{-- Progress --}}
            <div class="flex w-64 flex-col justify-center rounded-2xl bg-[#f0f5fa] p-4 dark:bg-gray-800">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-800 dark:text-white">{{ __('admin.progress') }}</span>
                    <span class="text-sm font-bold text-blue-600 dark:text-blue-400">
                        {{ round(($completedCount / $totalTasks) * 100) }}%
                    </span>
                </div>
                <div class="h-2 w-full overflow-hidden rounded-full bg-blue-100 dark:bg-gray-700">
                    <div
                        class="h-full rounded-full bg-[#4a90e2] transition-all duration-300"
                        style="width: {{ ($completedCount / $totalTasks) * 100 }}%"
                    ></div>
                </div>
                <div class="mt-2 text-right">
                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                        {{ $completedCount }} of {{ $totalTasks }} tasks completed
                    </span>
                </div>
            </div>
        </div>

        {{-- Checklist cards grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($checklistTasks as $task)
                <div class="relative rounded-xl border bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 {{ $task['isCompleted'] ? 'border-green-200 dark:border-green-800' : 'border-gray-200' }}">
                    {{-- Icon --}}
                    @php
                        $iconColor = $task['isCompleted'] ? 'text-green-600 dark:text-green-400' : 'text-[#4a90e2] dark:text-blue-400';
                        $bgColor = $task['isCompleted'] ? 'bg-green-50 dark:bg-green-900/20' : 'bg-[#f0f5fa] dark:bg-blue-900/20';
                    @endphp
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-lg {{ $bgColor }}">
                            @switch($task['key'])
                                @case('admin_profile')
                                    <svg class="h-5 w-5 {{ $iconColor }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                                    </svg>
                                    @break
                                @case('cities')
                                    <svg class="h-5 w-5 {{ $iconColor }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                                    </svg>
                                    @break
                                @case('taxes')
                                    <svg class="h-5 w-5 {{ $iconColor }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 0 1-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0 1 15 18.257V17.25m6-12V15a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 15V5.25m18 0A2.25 2.25 0 0 0 18.75 3H5.25A2.25 2.25 0 0 0 3 5.25m18 0V12a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 12V5.25" />
                                    </svg>
                                    @break
                                @case('cancellation_policy')
                                    <svg class="h-5 w-5 {{ $iconColor }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                    </svg>
                                    @break
                                @case('legal_policy')
                                    <svg class="h-5 w-5 {{ $iconColor }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                    </svg>
                                    @break
                                @case('commission')
                                    <svg class="h-5 w-5 {{ $iconColor }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0 1 11.186 0c1.1.128 1.907 1.077 1.907 2.185ZM9.75 9h.008v.008H9.75V9Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM14.25 15h.008v.008h-.008V15Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                    </svg>
                                    @break
                                @case('property_type_setup')
                                    <svg class="h-5 w-5 {{ $iconColor }}" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                    </svg>
                                    @break
                            @endswitch
                    </div>

                    {{-- Title & Description --}}
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
                        {{ $task['label'] }}
                    </h3>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $task['description'] }}
                    </p>

                    {{-- CTA Button --}}
                    <div class="mt-4">
                        @if ($task['isCompleted'])
                            <span class="inline-flex items-center gap-x-1 rounded-lg bg-green-50 px-3 py-1.5 text-xs font-medium text-green-700 dark:bg-green-900/20 dark:text-green-400">
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                Completed
                            </span>
                        @else
                            <a
                                href="{{ $task['route'] }}"
                                wire:navigate
                                class="inline-flex items-center gap-x-1 rounded-lg bg-gray-950 px-3.5 py-2 text-xs font-medium text-white shadow-sm transition hover:bg-black"
                            >
                                {{ $task['buttonLabel'] }}
                                <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
