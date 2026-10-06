@php
    use Illuminate\Support\Facades\Storage;
    use App\Models\Setting;

    $favicon = Setting::get('favicon');
    $faviconPath = is_array($favicon) ? ($favicon[0] ?? null) : $favicon;
    $faviconPath = is_array($faviconPath) ? ($faviconPath[0] ?? null) : $faviconPath;
    $faviconUrl = filled($faviconPath) && Storage::disk('public')->exists($faviconPath)
        ? Storage::disk('public')->url($faviconPath)
        : null;

    $appInitial = mb_strtoupper(mb_substr(config('app.name'), 0, 1));
@endphp

<div x-show="! $store.sidebar.isOpen" x-cloak class="flex w-full justify-center py-1">
    @if ($faviconUrl)
        <img src="{{ $faviconUrl }}" alt="{{ config('app.name') }}" class="h-10 w-10 object-contain">
    @else
        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-600 font-bold text-white">
            {{ $appInitial }}
        </div>
    @endif
</div>
