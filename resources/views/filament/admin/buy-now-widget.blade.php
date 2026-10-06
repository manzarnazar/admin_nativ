@if ($url)
    <div
        x-data="{ open: localStorage.getItem('buy-now-widget-open') !== 'false', visible: !!sessionStorage.getItem('buy-now-widget-seen') }"
        x-init="if (! visible) { setTimeout(() => { visible = true; sessionStorage.setItem('buy-now-widget-seen', '1'); }, 200); }"
        class="fixed bottom-6 right-6 z-50"
    >
        <a
            href="{{ $url }}"
            target="_blank"
            rel="noopener noreferrer"
            x-show="visible"
            x-transition:enter="transition duration-500 ease-[cubic-bezier(0.34,1.56,0.64,1)]"
            x-transition:enter-start="opacity-0 scale-50"
            x-transition:enter-end="opacity-100 scale-100"
            style="background: linear-gradient(to bottom, var(--brand-primary), color-mix(in srgb, var(--brand-primary) 82%, white));"
            class="buy-now-pulse relative flex h-14 items-center overflow-hidden rounded-2xl text-white shadow-lg"
        >
            {{-- Moving shine sweep: a slow, one-off diagonal highlight, not constant motion --}}
            <span class="buy-now-shine" aria-hidden="true"></span>

            {{-- Icon: fixed size, never unmounts, never animates --}}
            <span class="flex h-14 w-14 shrink-0 items-center justify-center">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20">
                    <x-heroicon-o-rocket-launch class="h-5 w-5" />
                </span>
            </span>

            {{-- Strip: only this expands/collapses, via max-width so the icon is untouched --}}
            <span
                x-show="open"
                x-transition:enter="transition-all ease-out duration-300"
                x-transition:enter-start="max-w-0 opacity-0"
                x-transition:enter-end="max-w-xs opacity-100"
                x-transition:leave="transition-all ease-in duration-200"
                x-transition:leave-start="max-w-xs opacity-100"
                x-transition:leave-end="max-w-0 opacity-0"
                class="flex min-w-0 flex-col justify-center overflow-hidden whitespace-nowrap py-2 pe-8 leading-tight"
            >
                <span class="text-sm font-semibold">{{ __('admin.buy_now') }}</span>
                @if ($message)
                    <span class="block truncate text-xs text-white/90">{{ $message }}</span>
                @endif
            </span>
        </a>

        {{-- Single persistent toggle: same chevron throughout, rotates 180° instead of swapping icons --}}
        <button
            type="button"
            x-show="visible"
            x-transition
            x-on:click.prevent.stop="open = ! open; localStorage.setItem('buy-now-widget-open', open)"
            style="background-color: var(--brand-btn-hover);"
            class="absolute -bottom-1.5 -right-1.5 flex h-6 w-6 items-center justify-center rounded-full text-white shadow ring-2 ring-white dark:ring-gray-900"
        >
            <x-heroicon-o-chevron-down
                class="h-3.5 w-3.5 transition-transform duration-300"
                x-bind:class="{ 'rotate-180': ! open }"
            />
        </button>
    </div>

    <style>
        @keyframes buy-now-pulse {
            0%, 92%, 100% { transform: scale(1); }
            94% { transform: scale(1.05); }
            96% { transform: scale(1); }
        }

        .buy-now-pulse {
            animation: buy-now-pulse 40s ease-in-out infinite;
        }

        .buy-now-shine {
            position: absolute;
            top: 0;
            left: 0;
            height: 100%;
            width: 40%;
            background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.35), transparent);
            transform: skewX(-18deg) translateX(-260%);
            animation: buy-now-shine 4.5s ease-in-out 1.2s infinite;
            pointer-events: none;
        }

        @keyframes buy-now-shine {
            0% { transform: skewX(-18deg) translateX(-260%); }
            18%, 100% { transform: skewX(-18deg) translateX(360%); }
        }

        @media (prefers-reduced-motion: reduce) {
            .buy-now-pulse,
            .buy-now-shine {
                animation: none;
            }
        }
    </style>
@endif
