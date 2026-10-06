<div>
    <style>
        @media (max-width: 1023px) {
            .tpr-row {
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 6px !important;
            }
            .tpr-label {
                width: auto !important;
                flex-shrink: unset !important;
                text-align: left !important;
            }
            .tpr-bar {
                width: 100% !important;
            }
            .tpr-axis-spacer {
                display: none !important;
            }
            .tpr-axis-scale {
                width: 100% !important;
            }
        }
    </style>
    @if (empty($rooms))
        <div style="text-align: center; padding: 40px 0; color: #94A3B8;">
            <p style="margin: 0; font-size: 14px;">{{ __('admin.no_data_available') }}</p>
        </div>
    @else
        <div style="display: flex; flex-direction: column; gap: 12px;">
            @foreach ($rooms as $index => $room)
                <div class="tpr-row" style="display: flex; align-items: center; gap: 16px;">
                    {{-- Room Name --}}
                    <div class="tpr-label" style="width: 200px; flex-shrink: 0; text-align: right;">
                        <span style="font-size: 13px; color: #475569; font-weight: 500; line-height: 20px;">
                            {{ $room['name'] }}
                        </span>
                    </div>

                    {{-- Bar --}}
                    <div class="tpr-bar" style="flex: 1; position: relative;"
                         x-data="{ show: false, hovered: false, currentWidth: 0 }"
                         x-init="setTimeout(() => { currentWidth = {{ $room['percentage'] }} }, 50)"
                         x-on:mouseenter="show = true; hovered = true"
                         x-on:mouseleave="show = false; hovered = false">
                        {{-- Background track --}}
                        <div style="width: 100%; background: #E8F1FD; border-radius: 8px; padding: 4px;">
                            {{-- Fill bar --}}
                            <div :style="{ background: hovered ? '#1A73E8' : '#60A5FA', width: currentWidth + '%', transition: 'width 1.5s cubic-bezier(0.16, 1, 0.3, 1), background 0.3s ease' }"
                                 style="min-width: 60px; width: 0%; border-radius: 8px; padding: 4px; display: flex; justify-content: flex-end; align-items: center;">
                                {{-- Number pill --}}
                                <div style="padding: 4px 12px; background: white; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center;">
                                    <span style="font-size: 14px; font-weight: 700; color: #0F172A; line-height: 20px;">
                                        {{ number_format($room['bookings']) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Tooltip --}}
                        <div x-show="show"
                             x-transition
                             style="position: absolute; top: -60px; right: 0; background: #1E293B; color: white; border-radius: 8px; padding: 8px 12px; font-size: 12px; z-index: 10; white-space: nowrap; pointer-events: none;">
                            <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 2px;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: #4ADE80; display: inline-block;"></span>
                                Revenue : {{ number_format($room['revenue']) }}
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <span style="width: 8px; height: 8px; border-radius: 50%; background: #FB923C; display: inline-block;"></span>
                                Bookings : {{ number_format($room['bookings']) }}
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            {{-- X-axis scale --}}
            <div style="display: flex; align-items: flex-start; gap: 16px;">
                {{-- Empty space matching room name width --}}
                <div class="tpr-axis-spacer" style="width: 200px; flex-shrink: 0;"></div>

                {{-- Scale ticks --}}
                <div class="tpr-axis-scale" style="flex: 1; position: relative; height: 24px; border-top: 1px solid #E2E8F0;">
                    @foreach ($ticks as $tick)
                        <div style="position: absolute; left: {{ $scaleMax > 0 ? ($tick / $scaleMax) * 100 : 0 }}%; transform: translateX(-50%); top: 4px;">
                            <span style="font-size: 12px; color: #94A3B8; font-weight: 500;">
                                {{ number_format($tick) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
