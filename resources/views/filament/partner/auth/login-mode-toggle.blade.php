<div class="flex rounded-xl p-1 mb-2" style="background-color: rgba(107,114,128,0.12);">
    <button
        type="button"
        wire:click="$set('loginMode', 'phone')"
        @class([
            'flex-1 py-2 px-4 rounded-lg text-sm font-semibold transition-all duration-200',
            'bg-gray-900 dark:bg-white text-white dark:text-gray-900 shadow-sm' => $loginMode === 'phone',
            'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' => $loginMode !== 'phone',
        ])
    >
        {{ __('admin.phone') }}
    </button>
    <button
        type="button"
        wire:click="$set('loginMode', 'email')"
        @class([
            'flex-1 py-2 px-4 rounded-lg text-sm font-semibold transition-all duration-200',
            'bg-gray-900 dark:bg-white text-white dark:text-gray-900 shadow-sm' => $loginMode === 'email',
            'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' => $loginMode !== 'email',
        ])
    >
        {{ __('admin.email') }}
    </button>
</div>
