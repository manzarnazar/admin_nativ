@php
    use Illuminate\Support\Facades\Storage;
    use App\Models\Setting;

    $logo = Setting::get('logo');
    $logoPath = is_array($logo) ? ($logo[0] ?? null) : $logo;
    $logoPath = is_array($logoPath) ? ($logoPath[0] ?? null) : $logoPath;

    $logoUrl = filled($logoPath) && Storage::disk('public')->exists($logoPath)
        ? Storage::disk('public')->url($logoPath)
        : null;
@endphp

@if ($logoUrl)
    <img src="{{ $logoUrl }}" alt="{{ config('app.name') }}" class="h-10 w-auto object-contain">
@else
    <span class="text-lg font-semibold">{{ config('app.name') }}</span>
@endif
