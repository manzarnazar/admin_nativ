<x-filament-panels::page>
    {{-- Back Link --}}
    <div class="-mt-4 mb-3">
        <a
            href="{{ \App\Filament\Pages\RolesPermissionsManage::getUrl() }}"
            wire:navigate
            class="inline-flex items-center gap-1 text-sm text-gray-500 transition hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            {{ __('admin.back_to_roles_permissions') }}
        </a>
    </div>

    {{-- Page Header with Buttons --}}
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-gray-950 dark:text-white">
            {{ $this->record ? __('admin.edit_role') : __('admin.create_new_role') }}
        </h1>
        <div class="flex items-center gap-3">
            <button
                type="button"
                wire:click="cancel"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                {{ __('admin.cancel') }}
            </button>
            <button
                type="button"
                wire:click="saveRole"
                class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-700"
            >
                <x-heroicon-o-bookmark-square class="h-4 w-4" />
                {{ __('admin.save_role') }}
            </button>
        </div>
    </div>

    {{-- Top Row: Role Details + Assign Staff --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        {{-- Role Details Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.role_details') }}</h2>
            </div>
            <div class="space-y-5 p-6">
                {{-- Role Name --}}
                <div class="flex flex-col gap-1">
                    <label class="inline-flex items-center gap-0.5 text-base text-gray-900 dark:text-gray-100">
                        {{ __('admin.role_name') }}
                        <span class="text-lg font-medium text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model.live="roleName"
                        placeholder="{{ __('admin.role_name_placeholder') }}"
                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-base text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500"
                    />
                    @error('roleName')
                        <p class="text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Description --}}
                <div class="flex flex-col gap-1">
                    <label class="inline-flex items-center gap-0.5 text-base text-gray-900 dark:text-gray-100">
                        {{ __('admin.description') }}
                        <span class="text-lg font-medium text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model.live="description"
                        placeholder="{{ __('admin.role_description_placeholder') }}"
                        class="block w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-base text-gray-900 placeholder-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-500"
                    />
                    @error('description')
                        <p class="text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
            </div>
        </div>

        {{-- Assign Staff Card --}}
        <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="rounded-t-2xl border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-800">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.assign_staff') }}</h2>
            </div>
            <div class="space-y-5 p-6">
                {{-- Warning Banner --}}
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                    <p class="text-sm font-semibold text-red-600 dark:text-red-400">
                        {{ __('admin.assign_staff_warning') }}
                    </p>
                </div>

                {{-- Staff Dropdown --}}
                <div class="flex flex-col gap-1" x-data="{ open: false }" @click.outside="open = false">
                    <label class="inline-flex items-center gap-0.5 text-base text-gray-900 dark:text-gray-100">
                        {{ __('admin.select_staff_member') }}
                        <span class="text-lg font-medium text-red-500">*</span>
                    </label>

                    <div class="relative">
                        <button
                            type="button"
                            @click="open = !open"
                            class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-base text-gray-500 focus:border-primary-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400"
                        >
                            <span>
                                @if (count($this->selectedStaff) > 0)
                                    {{ count($this->selectedStaff) }} {{ __('admin.staff_selected') }}
                                @else
                                    {{ __('admin.select_staff_placeholder') }}
                                @endif
                            </span>
                            <x-heroicon-o-chevron-down class="h-5 w-5" />
                        </button>

                        <div
                            x-show="open"
                            x-transition
                            class="absolute z-20 mt-1 w-full rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                        >
                            {{-- Search --}}
                            <div class="p-3 border-b border-gray-200 dark:border-gray-700">
                                <input
                                    type="text"
                                    wire:model.live.debounce.300ms="staffSearch"
                                    placeholder="{{ __('admin.search_staff') }}"
                                    class="block w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                                />
                            </div>

                            {{-- Staff List --}}
                            <div class="max-h-60 overflow-y-auto">
                                @php $staffList = $this->getAvailableStaff(); @endphp
                                @forelse ($staffList as $staff)
                                    <button
                                        type="button"
                                        wire:click="toggleStaff({{ $staff['id'] }})"
                                        class="flex w-full items-center gap-3 px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 @if(in_array($staff['id'], $this->selectedStaff)) bg-primary-50 dark:bg-primary-900/20 @endif"
                                    >
                                        <div class="flex h-5 w-5 flex-shrink-0 items-center justify-center rounded border @if(in_array($staff['id'], $this->selectedStaff)) border-primary-600 bg-primary-600 @else border-gray-400 bg-white dark:bg-gray-700 @endif">
                                            @if(in_array($staff['id'], $this->selectedStaff))
                                                <x-heroicon-s-check class="h-3 w-3 text-white" />
                                            @endif
                                        </div>
                                        <div class="text-left">
                                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $staff['name'] }}</div>
                                            <div class="text-xs text-gray-500">{{ $staff['email'] }}</div>
                                        </div>
                                    </button>
                                @empty
                                    <div class="px-4 py-6 text-center text-sm text-gray-400">
                                        {{ __('admin.no_staff_available') }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    {{-- Selected Tags --}}
                    @if (count($this->selectedStaff) > 0)
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($this->getSelectedStaffDetails() as $staff)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">
                                    {{ $staff['name'] }}
                                    <button type="button" wire:click="toggleStaff({{ $staff['id'] }})" class="hover:text-primary-900">
                                        <x-heroicon-s-x-mark class="h-3 w-3" />
                                    </button>
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Permissions Table --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 overflow-hidden">
        {{-- Header --}}
        <div class="border-b border-gray-200 bg-gray-50 px-6 py-5 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">{{ __('admin.permissions') }}</h2>
                <span class="text-lg font-semibold text-gray-950 dark:text-white">
                    {{ $this->getConfiguredModulesCount() }} {{ __('admin.modules_configured') }}
                </span>
            </div>
        </div>

        {{-- Column Headers --}}
        <div class="border-b border-gray-200 bg-gray-100 px-6 py-4 dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center gap-7">
                <div class="flex flex-1 items-center gap-2">
                    <span class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ __('admin.permissions_modules') }}
                    </span>
                    <div class="group relative">
                        <x-heroicon-o-question-mark-circle class="h-5 w-5 cursor-pointer text-gray-400 hover:text-gray-600" />
                        <div class="absolute left-6 top-0 z-10 hidden w-72 rounded-lg bg-gray-900 p-3 text-xs text-white shadow-lg group-hover:block">
                            {{ __('admin.permissions_tooltip') }}
                        </div>
                    </div>
                </div>
                @foreach (['VIEW', 'CREATE', 'EDIT', 'DELETE'] as $col)
                    <div class="w-48 text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ $col }}
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Module Rows --}}
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($this->getModules() as $module)
                <div class="flex items-center gap-7 px-6 py-5 hover:bg-gray-50/50 dark:hover:bg-gray-800/30">

                    {{-- Module Name + Row Checkbox --}}
                    <div class="flex flex-1 items-center gap-4">
                        <button
                            type="button"
                            wire:click="toggleAllActions('{{ $module->slug }}')"
                            class="flex h-6 w-6 flex-shrink-0 items-center justify-center rounded border transition
                                @if($this->isRowFullyChecked($module->slug))
                                    border-primary-600 bg-primary-600
                                @elseif($this->isRowChecked($module->slug))
                                    border-primary-400 bg-primary-100 dark:bg-primary-900/30
                                @else
                                    border-gray-400 bg-gray-50 dark:border-gray-500 dark:bg-gray-700
                                @endif"
                        >
                            @if($this->isRowFullyChecked($module->slug))
                                <x-heroicon-s-check class="h-3.5 w-3.5 text-white" />
                            @elseif($this->isRowChecked($module->slug))
                                <span class="h-2 w-2 rounded-sm bg-primary-500"></span>
                            @endif
                        </button>

                        <div class="min-h-12 flex flex-col justify-center gap-0.5">
                            <span class="text-base font-semibold text-gray-950 dark:text-white">{{ $module->label }}</span>
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $module->description }}</span>
                        </div>
                    </div>

                    {{-- Action Checkboxes --}}
                    @foreach (['view', 'create', 'edit', 'delete'] as $action)
                        <div class="w-48">
                            @if (in_array($action, $module->actions, true))
                                <button
                                    type="button"
                                    wire:click="$set('permissions.{{ $module->slug }}.{{ $action }}', {{ !($permissions[$module->slug][$action] ?? false) ? 'true' : 'false' }})"
                                    class="flex h-6 w-6 items-center justify-center rounded border transition
                                        @if($permissions[$module->slug][$action] ?? false)
                                            border-primary-600 bg-primary-600
                                        @else
                                            border-gray-400 bg-gray-50 dark:border-gray-500 dark:bg-gray-700
                                        @endif"
                                >
                                    @if($permissions[$module->slug][$action] ?? false)
                                        <x-heroicon-s-check class="h-3.5 w-3.5 text-white" />
                                    @endif
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
