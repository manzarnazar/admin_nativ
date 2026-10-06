<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Profile Card --}}
        <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
            {{-- Header: Avatar + Name + Edit Button --}}
            <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                <div class="flex items-center gap-x-4">
                    <img
                        src="{{ ($user->avatar && str_starts_with($user->avatar, 'avatars/')) ? asset('storage/' . $user->avatar) : asset('avatars/defaultUser.svg') }}"
                        alt="{{ $user->name }}"
                        class="h-12 w-12 rounded-full object-cover"
                    />
                    <span class="text-base font-semibold text-gray-900 dark:text-white">
                        {{ $user->name }}
                    </span>
                </div>

                {{ $this->editProfileAction }}
            </div>

            {{-- Contact Info: Phone + Email --}}
            <div class="flex flex-wrap gap-8 px-6 py-4">
                {{-- Phone --}}
                <div class="flex items-center gap-x-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20">
                        <x-heroicon-o-phone class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.phone') }}</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ ($user->dial_code ? $user->dial_code . ' ' : '') . ($user->phone ?? __('admin.not_set')) }}
                        </p>
                    </div>
                </div>

                {{-- Email --}}
                <div class="flex items-center gap-x-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20">
                        <x-heroicon-o-envelope class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ __('admin.email') }}</p>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ $user->email }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Change Password --}}
        <x-filament::section>
            <x-slot name="heading">{{ __('admin.change_password') }}</x-slot>

            {{ $this->passwordForm }}

            <div class="mt-4 flex justify-end">
                <x-filament::button
                    wire:click="updatePassword"
                    wire:loading.attr="disabled"
                    wire:target="updatePassword"
                    :disabled="\App\Support\DemoMode::isActive()"
                >
                    {{ __('admin.update_password') }}
                </x-filament::button>
            </div>
        </x-filament::section>
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
