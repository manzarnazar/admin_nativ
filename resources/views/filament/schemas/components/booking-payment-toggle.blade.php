@php
    $currentMethod = $get('payment_method') ?? 'cash';
@endphp

<div class="inline-flex w-full gap-1 rounded-xl p-1" style="background-color: var(--brand-primary-light);">
    <button
        type="button"
        wire:click="$set('data.payment_method', 'cash')"
        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
        style="{{ $currentMethod === 'cash' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
    >
        {{ __('admin.cash') }}
    </button>

    <button
        type="button"
        wire:click="$set('data.payment_method', 'upi')"
        class="flex-1 inline-flex items-center justify-center gap-1.5 rounded-lg px-4 py-2 text-sm font-medium transition"
        style="{{ $currentMethod === 'upi' ? 'background-color: var(--brand-btn); color: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.1);' : 'color: #4b5563;' }}"
    >
        {{ __('admin.upi') }}
    </button>
</div>
