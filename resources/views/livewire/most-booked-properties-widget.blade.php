<div style="background-color: white; border-radius: 12px; padding: 16px; border: 1px solid #F1F5F9; box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; flex-direction: column; height: 100%;">
    {{-- Header --}}
    <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="background-color: #F7F7F7; border-radius: 8px; padding: 12px; display: flex; align-items: center; justify-content: center;">
                <x-phosphor-ticket style="width: 24px; height: 24px; color: #555555;" />
            </div>
            <h3 style="font-size: 15px; font-weight: 700; color: #0F172A; margin: 0;">{{ __('admin.most_booked_properties') }}</h3>
        </div>
        <select wire:model.live="timeFilter" style="border: 1px solid #E2E8F0; border-radius: 6px; padding: 4px 8px; font-size: 13px; color: #64748B; background-color: white; cursor: pointer; outline: none;">
            <option value="last_7_days">{{ __('admin.last_7_days') }}</option>
            <option value="last_30_days">{{ __('admin.last_30_days') }}</option>
            <option value="this_month">{{ __('admin.this_month') }}</option>
            <option value="this_year">{{ __('admin.this_year') }}</option>
        </select>
    </div>

    {{-- Properties list --}}
    <div style="flex: 1; display: flex; flex-direction: column; gap: 10px;">
        @forelse ($properties as $property)
            @php
                $primaryImage = $property->primaryImages->firstWhere('media_type', 'image') ?? $property->primaryImages->first();
                $imageUrl = $primaryImage ? asset('storage/'.$primaryImage->image_path) : null;
            @endphp
            <a href="{{ \App\Filament\Pages\AllPropertiesView::getUrl(['record' => $property->id]) }}" wire:navigate style="display: flex; align-items: center; gap: 12px; padding: 10px; background-color: #F8FAFC; border-radius: 10px; text-decoration: none; transition: background-color 0.15s;" onmouseover="this.style.backgroundColor='#EFF6FF'" onmouseout="this.style.backgroundColor='#F8FAFC'">
                {{-- Thumbnail --}}
                <div style="width: 52px; height: 52px; border-radius: 8px; overflow: hidden; flex-shrink: 0; background-color: #E2E8F0;">
                    @if ($imageUrl)
                        <img src="{{ $imageUrl }}" alt="{{ $property->name }}" style="width: 100%; height: 100%; object-fit: cover;" />
                    @else
                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center;">
                            <x-heroicon-o-building-office-2 style="width: 24px; height: 24px; color: #94A3B8;" />
                        </div>
                    @endif
                </div>

                {{-- Info --}}
                <div style="flex: 1; min-width: 0;">
                    <p style="font-size: 13px; font-weight: 600; color: #0F172A; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $property->name }}
                    </p>
                    @if ($property->refCity)
                        <p style="font-size: 12px; color: #64748B; margin: 3px 0 0 0;">{{ $property->refCity->name }}</p>
                    @endif
                </div>

                {{-- Booked + occupancy badge --}}
                <div style="flex-shrink: 0;">
                    <span style="font-size: 12px; font-weight: 600; color: #16A34A; background-color: #DCFCE7; padding: 4px 10px; border-radius: 20px; white-space: nowrap;">
                        {{ number_format($property->bookings_count) }} {{ __('admin.bookings') }} · {{ (int) $property->occupancy_percentage }}% {{ __('admin.occupancy') }}
                    </span>
                </div>
            </a>
        @empty
            <div style="text-align: center; padding: 40px 0; color: #94A3B8;">
                <p style="font-size: 14px; margin: 0;">{{ __('admin.no_bookings_yet') }}</p>
            </div>
        @endforelse
    </div>

    {{-- Pagination footer --}}
    @if ($properties->hasPages())
        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 16px; padding-top: 12px; border-top: 1px solid #F1F5F9;">
            <span style="font-size: 13px; color: #64748B;">
                {{ __('admin.showing_result') }} : {{ $properties->count() }}
            </span>
            <div style="display: flex; align-items: center; gap: 8px;">
                <button
                    wire:click="previousPage"
                    @if ($properties->onFirstPage()) disabled @endif
                    style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #E2E8F0; background-color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; {{ $properties->onFirstPage() ? 'opacity: 0.4; cursor: not-allowed;' : '' }}"
                >
                    <x-heroicon-o-chevron-left style="width: 14px; height: 14px; color: #64748B;" />
                </button>
                <div style="width: 32px; height: 32px; border-radius: 8px; background-color: #2563EB; display: flex; align-items: center; justify-content: center;">
                    <span style="font-size: 13px; font-weight: 600; color: white;">{{ $properties->currentPage() }}</span>
                </div>
                <button
                    wire:click="nextPage"
                    @unless ($properties->hasMorePages()) disabled @endunless
                    style="width: 32px; height: 32px; border-radius: 8px; border: 1px solid #E2E8F0; background-color: white; display: flex; align-items: center; justify-content: center; cursor: pointer; {{ !$properties->hasMorePages() ? 'opacity: 0.4; cursor: not-allowed;' : '' }}"
                >
                    <x-heroicon-o-chevron-right style="width: 14px; height: 14px; color: #64748B;" />
                </button>
            </div>
        </div>
    @else
        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 16px; padding-top: 12px; border-top: 1px solid #F1F5F9;">
            <span style="font-size: 13px; color: #64748B;">
                {{ __('admin.showing_result') }} : {{ $properties->count() }}
            </span>
        </div>
    @endif
</div>
