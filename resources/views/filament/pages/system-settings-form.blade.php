<x-filament-panels::page>
    @if(method_exists($this, 'getTabs') && count($this->getTabs()) > 1)
        {{-- Tab Switcher --}}
        <div class="mb-6 tab-scroll-wrapper">
            <div class="inline-flex gap-1 rounded-xl p-1 dark:bg-gray-800 tab-pill-row" style="background-color: var(--brand-primary-light);">
                @foreach($this->getTabs() as $tab)
                    <button
                        type="button"
                        wire:click="switchTab('{{ $tab['slug'] }}')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition tab-pill-btn"
                        style="{{ $currentTab === $tab['slug'] ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
                    >
                        @if(isset($tab['icon']))
                            <x-dynamic-component :component="$tab['icon']" class="h-4 w-4" />
                        @endif
                        {{ $tab['label'] }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    @if ($currentTab === 'cron-jobs')
        @php
            $schedulerCmd = $this->schedulerCommand;
            $queueCmd = $this->queueCommand;
        @endphp
        <div class="space-y-4">
            
            {{-- Commands --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start gap-4">
                    <div class="rounded-lg bg-green-100 p-3 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                        <x-heroicon-o-command-line class="h-6 w-6" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('admin.your_cron_commands') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('admin.cron_commands_description_prefix') }} <strong>{{ __('admin.every_minute') }}</strong>.
                        </p>
                        <div class="mt-4 space-y-3">
                            <div>
                                <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('admin.cron_1_scheduler') }}</p>
                                <div x-data="{ copied: false, cmd: @js($schedulerCmd) }" class="flex gap-2">
                                    <code class="flex-1 overflow-x-auto rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $schedulerCmd }}</code>
                                    <button
                                        type="button"
                                        @click="navigator.clipboard.writeText(cmd); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    >
                                        <span x-show="!copied">{{ __('admin.copy') }}</span>
                                        <span x-show="copied">{{ __('admin.copied') }}</span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <p class="mb-1 text-xs font-medium text-gray-500 dark:text-gray-400">{{ __('admin.cron_2_queue_worker') }}</p>
                                <div x-data="{ copied: false, cmd: @js($queueCmd) }" class="flex gap-2">
                                    <code class="flex-1 overflow-x-auto rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-800 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $queueCmd }}</code>
                                    <button
                                        type="button"
                                        @click="navigator.clipboard.writeText(cmd); copied = true; setTimeout(() => copied = false, 1500)"
                                        class="shrink-0 rounded-lg border border-gray-200 bg-white px-3 py-2 text-xs font-medium text-gray-600 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                    >
                                        <span x-show="!copied">{{ __('admin.copy') }}</span>
                                        <span x-show="copied">{{ __('admin.copied') }}</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Status & Statistics Cards --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                
                {{-- Cron Status Card --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 flex flex-col justify-between h-full">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Cron Status</p>
                    <div class="mt-2">
                        @if($this->schedulerHealthy)
                            <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-bold text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/30 dark:text-green-400 dark:ring-green-500/20">Active</span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-bold text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-900/30 dark:text-red-400 dark:ring-red-500/20">Inactive</span>
                        @endif
                    </div>
                    <p class="mt-3 text-[10px] text-gray-500 dark:text-gray-400">Last heartbeat: {{ $this->schedulerLastRun !== __('admin.never') ? $this->schedulerLastRun : 'Never' }}</p>
                </div>

                {{-- Queue Status Card --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 flex flex-col justify-between h-full">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Queue Status</p>
                        <span class="text-[10px] font-semibold uppercase text-gray-400">{{ config('queue.default', 'database') }}</span>
                    </div>
                    <div class="mt-2">
                        @if($this->queueHealthy)
                            <span class="inline-flex items-center rounded-md bg-green-50 px-2 py-1 text-xs font-bold text-green-700 ring-1 ring-inset ring-green-600/20 dark:bg-green-900/30 dark:text-green-400 dark:ring-green-500/20">Active</span>
                        @else
                            <span class="inline-flex items-center rounded-md bg-red-50 px-2 py-1 text-xs font-bold text-red-700 ring-1 ring-inset ring-red-600/20 dark:bg-red-900/30 dark:text-red-400 dark:ring-red-500/20">Inactive</span>
                        @endif
                    </div>
                    <p class="mt-3 text-[10px] text-gray-500 dark:text-gray-400">Last heartbeat: {{ $this->queueLastRun !== __('admin.never') ? $this->queueLastRun : 'Never' }}</p>
                </div>

                {{-- Pending Jobs Card --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 flex flex-col justify-between h-full">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Pending Jobs</p>
                    <div class="mt-2">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ number_format($this->pendingJobsCount) }}</h3>
                    </div>
                    <p class="mt-3 text-[10px] text-gray-500 dark:text-gray-400">
                        @if($this->pendingJobsCount > 0 && $this->oldestPendingJobDate)
                            Oldest: {{ $this->oldestPendingJobDate }}
                        @else
                            &nbsp;
                        @endif
                    </p>
                </div>

                {{-- Failed Jobs Card --}}
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 flex flex-col justify-between h-full">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Failed Jobs</p>
                    <div class="mt-2">
                        <h3 class="text-lg font-bold {{ $this->failedJobsCount > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-white' }}">{{ number_format($this->failedJobsCount) }}</h3>
                    </div>
                    <p class="mt-3 text-[10px] text-transparent select-none">&nbsp;</p>
                </div>
            </div>

            {{-- Scheduled Tasks Table --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900 overflow-hidden mt-4">
                <div class="border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('admin.scheduled_tasks') }}</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('admin.scheduled_tasks_description') }}</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
                        <thead class="bg-gray-50 dark:bg-gray-900/50 text-gray-700 dark:text-gray-200">
                            <tr>
                                <th class="px-6 py-3 font-medium">{{ __('admin.task') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('admin.command') }}</th>
                                <th class="px-6 py-3 font-medium">{{ __('admin.schedule') }}</th>
                                <th class="px-6 py-3 font-medium text-right">{{ __('admin.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($this->getTasks() as $task)
                                <tr class="hover:bg-gray-50 dark:hover:bg-white/5 transition">
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $task['name'] }}</div>
                                        <div class="text-xs mt-1">{{ $task['description'] }}</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <code class="text-xs text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-500/10 px-2 py-1 rounded">
                                            {{ $task['command'] }}
                                        </code>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        {{ $task['schedule'] }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <x-filament::button
                                            color="success"
                                            size="sm"
                                            icon="heroicon-m-play"
                                            wire:click="runCommand('{{ $task['command'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="runCommand('{{ $task['command'] }}')"
                                        >
                                            {{ __('admin.run_now') }}
                                        </x-filament::button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    @elseif ($currentTab === 'demo-reset')
        <div class="space-y-4">

            {{-- Demo Reset --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">{{ __('admin.demo_reset_section_heading') }}</h2>

                <div class="mt-4 divide-y divide-gray-100 dark:divide-gray-800">
                    {{-- Update Baseline --}}
                    <div class="flex items-start gap-4 pb-6">
                        <div class="rounded-lg bg-blue-100 p-3 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                            <x-heroicon-o-camera class="h-6 w-6" />
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('admin.update_baseline') }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('admin.update_baseline_description_prefix') }} <strong>{{ __('admin.current_database_and_uploaded_assets') }}</strong> {{ __('admin.update_baseline_description_suffix') }}
                            </p>

                            @if ($baselineCapturedAt)
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                    {{ __('admin.last_captured_prefix') }}
                                    <span x-data x-text="new Date('{{ $baselineCapturedAt }}').toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })"></span>
                                </p>
                            @else
                                <p class="mt-2 text-xs text-amber-500">
                                    {{ __('admin.no_baseline_captured_yet') }}
                                </p>
                            @endif

                            {{-- Warning --}}
                            <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-900/20">
                                <p class="text-xs text-amber-700 dark:text-amber-400">
                                    <strong>{{ __('admin.important_label') }}</strong> {{ __('admin.baseline_warning_prefix') }}
                                    <strong>{{ __('admin.reset_then_update_baseline') }}</strong>.
                                </p>
                            </div>

                            <div class="mt-4">
                                <x-filament::button
                                    color="primary"
                                    wire:click="captureBaseline"
                                    wire:loading.attr="disabled"
                                    wire:target="captureBaseline"
                                >
                                    <span wire:loading.remove wire:target="captureBaseline">{{ __('admin.update_baseline') }}</span>
                                    <span wire:loading wire:target="captureBaseline">{{ __('admin.capturing_please_wait') }}</span>
                                </x-filament::button>
                            </div>
                        </div>
                    </div>

                    {{-- Reset Demo --}}
                    <div class="flex items-start gap-4 pt-6">
                        <div class="rounded-lg bg-red-100 p-3 text-red-600 dark:bg-red-900/30 dark:text-red-400">
                            <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                        </div>
                        <div class="flex-1">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('admin.reset_demo_data_heading') }}</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                {{ __('admin.reset_demo_data_description') }}
                            </p>

                            @if ($lastResetAt)
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                    {{ __('admin.last_reset_prefix') }}
                                    <span x-data x-text="new Date('{{ $lastResetAt }}').toLocaleString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })"></span>
                                </p>
                            @else
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">
                                    {{ __('admin.never_reset') }}
                                </p>
                            @endif

                            <div class="mt-4">
                                <x-filament::button
                                    color="danger"
                                    wire:click="resetDemoData"
                                    wire:loading.attr="disabled"
                                    wire:target="resetDemoData"
                                    :disabled="! $baselineCapturedAt"
                                >
                                    <span wire:loading.remove wire:target="resetDemoData">{{ __('admin.reset_to_baseline') }}</span>
                                    <span wire:loading wire:target="resetDemoData">{{ __('admin.restoring_please_wait') }}</span>
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Buy Now Widget --}}
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-start gap-4">
                    <div class="rounded-lg bg-[var(--brand-primary-light)] p-3 text-[var(--brand-primary)]">
                        <x-heroicon-o-shopping-bag class="h-6 w-6" />
                    </div>
                    <div class="flex-1">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-white">{{ __('admin.buy_now_widget_heading') }}</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ __('admin.buy_now_widget_description') }}
                        </p>

                        <form wire:submit="saveBuyNowSettings" class="mt-4 space-y-4">
                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.buy_now_message_label') }}</label>
                                <x-filament::input.wrapper class="mt-1" :valid="! $errors->has('buyNowMessage')">
                                    <x-filament::input
                                        type="text"
                                        wire:model="buyNowMessage"
                                    />
                                </x-filament::input.wrapper>
                                @error('buyNowMessage') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ __('admin.buy_now_url_label') }}</label>
                                <x-filament::input.wrapper class="mt-1" :valid="! $errors->has('buyNowUrl')">
                                    <x-filament::input
                                        type="url"
                                        wire:model="buyNowUrl"
                                        placeholder="https://..."
                                    />
                                </x-filament::input.wrapper>
                                @error('buyNowUrl') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                            </div>

                            <x-filament::button
                                type="submit"
                                color="primary"
                                wire:loading.attr="disabled"
                                wire:target="saveBuyNowSettings"
                            >
                                <span wire:loading.remove wire:target="saveBuyNowSettings">{{ __('admin.save_buy_now_settings') }}</span>
                                <span wire:loading wire:target="saveBuyNowSettings">{{ __('admin.saving') }}</span>
                            </x-filament::button>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    @else
        <form wire:submit="save">
            {{ $this->form }}

            <div class="mt-6 flex justify-end">
                <x-filament::button type="submit" :disabled="! $this::canEdit()">
                    {{ __('admin.save_settings') }}
                </x-filament::button>
            </div>
        </form>
    @endif
</x-filament-panels::page>
