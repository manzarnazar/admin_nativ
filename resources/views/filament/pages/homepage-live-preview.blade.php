@php
use App\Models\Setting;
$baseFrontendUrl = rtrim(Setting::get('frontend_web_url') ?: config('services.frontend.url') ?: url('/'), '/');
$domain = str($baseFrontendUrl)->after('//')->before('/')->toString();

$parsedUrl = parse_url($baseFrontendUrl);
$hasPath = isset($parsedUrl['path']) && strlen($parsedUrl['path']) > 1;
$locale = app()->getLocale();
$frontendUrl = $hasPath ? $baseFrontendUrl : "{$baseFrontendUrl}/{$locale}";
@endphp

<div
    x-data="{
        platform: 'web',
        statusTime: '',
        updateStatusTime() {
            this.statusTime = new Date().toLocaleTimeString([], {
                hour: 'numeric',
                minute: '2-digit',
            });
        },
        init() {
            this.updateStatusTime();
            setInterval(() => this.updateStatusTime(), 60000);
        },
    }">

    {{-- Modal Header: Title left, Platform toggle right, with separator --}}
    <div class="flex items-center justify-between pb-4 mb-5 border-b border-gray-200 dark:border-gray-700">
        <h2 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">{{ __('admin.live_landing_page_preview') }}</h2>
        <div class="flex items-center gap-2 flex-shrink-0">
            <button
                type="button"
                @click="platform = 'web'"
                :class="platform === 'web'
                    ? 'bg-primary-600 text-white border border-primary-600'
                    : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                <x-heroicon-o-computer-desktop class="w-4 h-4" />
                {{ __('admin.web') }}
            </button>
            <button
                type="button"
                @click="platform = 'app'"
                title="{{ __('admin.app_content_preview_note') }}"
                :class="platform === 'app'
                    ? 'bg-primary-600 text-white border border-primary-600'
                    : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700'"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors">
                <x-heroicon-o-device-phone-mobile class="w-4 h-4" />
                {{ __('admin.app') }}
            </button>
        </div>
    </div>

    {{-- Web Preview --}}
    <div x-show="platform === 'web'" x-cloak>
        <div class="rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm">
            {{-- Browser Chrome --}}
            <div class="flex items-center gap-3 px-4 py-2.5 bg-gray-100 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <div class="flex gap-1.5 flex-shrink-0">
                    <div class="w-3 h-3 rounded-full bg-red-400"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                    <div class="w-3 h-3 rounded-full bg-green-400"></div>
                </div>
                <div class="flex flex-1 justify-center">
                    <div class="flex items-center gap-1.5 px-3 py-1 bg-white dark:bg-gray-700 rounded-md text-xs text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-600 w-64">
                        <x-heroicon-s-lock-closed class="w-3 h-3 text-gray-400 flex-shrink-0" />
                        <span class="truncate">{{ $domain }}</span>
                    </div>
                </div>
                <span class="text-gray-400 text-sm flex-shrink-0">+</span>
            </div>
            {{-- Browser Content --}}
            <div style="height: 600px; overflow: hidden; position: relative;" class="bg-white dark:bg-gray-900">
                <iframe
                    src="{{ $frontendUrl }}?preview_platform=web"
                    style="width: 143%; height: 858px; transform: scale(0.7); transform-origin: top left; border: 0; background: white; position: absolute; top: 0; left: 0;"
                    title="{{ __('admin.web') }} Preview"
                    loading="lazy"></iframe>
            </div>
        </div>
    </div>

    {{-- App Preview --}}
    {{--
        Geometry (all derived, don't change one without the others):
        - Device width 300px = 2.5px metal edge x2 + 11px bezel x2 + 273px screen
        - Scale = 273 / 390 = 0.7 exactly
        - Screen height = 844 x 0.7 = 590.8px -> device height 617.8px
        - iframe starts after the 38px status strip; 38 / 0.7 = 54.3px in iframe coordinates
        - Status bar strip (z-10, bg-white) reserves the app safe-area so it doesn't cover app content
    --}}
    <div x-show="platform === 'app'" x-cloak>
        <div class="flex justify-center py-4">
            <div class="relative" style="width: 300px; height: 617.8px;">

                {{-- Side buttons (rendered behind the frame, sticking out of it) --}}
                <div class="absolute rounded-l-md" style="left: -2.5px; top: 108px; width: 3px; height: 26px; background: linear-gradient(90deg, #4b5563, #1f2937);"></div>
                <div class="absolute rounded-l-md" style="left: -2.5px; top: 152px; width: 3px; height: 44px; background: linear-gradient(90deg, #4b5563, #1f2937);"></div>
                <div class="absolute rounded-l-md" style="left: -2.5px; top: 206px; width: 3px; height: 44px; background: linear-gradient(90deg, #4b5563, #1f2937);"></div>
                <div class="absolute rounded-r-md" style="right: -2.5px; top: 168px; width: 3px; height: 72px; background: linear-gradient(270deg, #4b5563, #1f2937);"></div>

                {{-- Titanium edge --}}
                <div
                    class="relative w-full h-full"
                    style="border-radius: 47px; padding: 2.5px; box-sizing: border-box;
                           background: linear-gradient(145deg, #6b7280 0%, #374151 25%, #111827 50%, #374151 75%, #6b7280 100%);
                           box-shadow: 0 30px 60px -15px rgba(0,0,0,0.55), 0 0 0 0.5px rgba(255,255,255,0.08);">
                    {{-- Bezel --}}
                    <div class="w-full h-full" style="border-radius: 44.5px; padding: 11px; box-sizing: border-box; background: #0b0f19;">

                        {{-- Screen --}}
                        <div class="w-full h-full overflow-hidden relative bg-white" style="border-radius: 34px;">

                            {{-- Status bar strip: reserves safe-area space so the island never covers app content --}}
                            <div class="absolute top-0 left-0 w-full z-10 flex items-end justify-between bg-white" style="height: 38px; padding: 0 22px 4px 26px;">
                                <span x-text="statusTime" style="font-size: 12px; font-weight: 600; color: #111827; font-variant-numeric: tabular-nums;">9:41</span>
                                <span class="flex items-center" style="gap: 5px;">
                                    {{-- Signal --}}
                                    <svg width="15" height="10" viewBox="0 0 15 10" fill="#111827">
                                        <rect x="0" y="6" width="2.5" height="4" rx="0.8" />
                                        <rect x="4" y="4" width="2.5" height="6" rx="0.8" />
                                        <rect x="8" y="2" width="2.5" height="8" rx="0.8" />
                                        <rect x="12" y="0" width="2.5" height="10" rx="0.8" />
                                    </svg>
                                    {{-- Wifi --}}
                                    <svg width="14" height="10" viewBox="0 0 14 10" fill="#111827">
                                        <path d="M7 8.6a1.4 1.4 0 100 2.8 1.4 1.4 0 000-2.8z" transform="translate(0,-1.8)" />
                                        <path d="M7 5.2c1.3 0 2.5.5 3.4 1.3l-1.2 1.2A3.3 3.3 0 007 6.9c-.9 0-1.7.3-2.2.8L3.6 6.5A4.9 4.9 0 017 5.2z" transform="translate(0,-1.8)" />
                                        <path d="M7 1.8c2.2 0 4.2.8 5.7 2.2l-1.2 1.2A6.6 6.6 0 007 3.5c-1.7 0-3.3.6-4.5 1.7L1.3 4A8.2 8.2 0 017 1.8z" transform="translate(0,-1.8)" />
                                    </svg>
                                    {{-- Battery --}}
                                    <svg width="23" height="11" viewBox="0 0 23 11">
                                        <rect x="0.5" y="0.5" width="19" height="10" rx="3" fill="none" stroke="#111827" stroke-opacity="0.4" />
                                        <rect x="2" y="2" width="14" height="7" rx="1.8" fill="#111827" />
                                        <path d="M21 3.5v4a2.2 2.2 0 000-4z" fill="#111827" fill-opacity="0.4" />
                                    </svg>
                                </span>
                            </div>

                            <iframe
                                src="{{ $frontendUrl }}?preview_platform=app"
                                class="border-0 absolute left-0"
                                style="top: 38px; width: 390px; height: 789.7px; transform: scale(0.7); transform-origin: 0 0;"
                                title="{{ __('admin.app') }} Content Preview"
                                loading="lazy"></iframe>

                            {{-- Dynamic Island (sits inside the status bar strip, not over content) --}}
                            <!-- <div
                                class="absolute left-1/2 -translate-x-1/2 z-20 flex items-center justify-end"
                                style="top: 8px; width: 84px; height: 25px; border-radius: 9999px; background: #000; padding-right: 7px;">
                                {{-- Camera lens --}}
                                <div style="width: 11px; height: 11px; border-radius: 9999px; background: radial-gradient(circle at 35% 35%, #1e293b 0%, #000 60%);"></div>
                            </div> -->

                            {{-- Home indicator (on-screen, like the real thing) --}}
                            <div class="absolute left-1/2 -translate-x-1/2 z-10 pointer-events-none" style="bottom: 6px; width: 94px; height: 4px; border-radius: 9999px; background: rgba(0,0,0,0.75);"></div>

                            {{-- Glass glare --}}
                            <div class="absolute inset-0 z-10 pointer-events-none" style="border-radius: 34px; background: linear-gradient(115deg, rgba(255,255,255,0.10) 0%, rgba(255,255,255,0.03) 22%, transparent 40%);"></div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>
