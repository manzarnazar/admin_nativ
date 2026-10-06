@php
    $rawColor = $attributes->get('color', 'blue');
    $color = in_array($rawColor, ['blue', 'red'], true) ? $rawColor : 'blue';
@endphp
<a
    href="{{ $attributes->get('href') }}"
    {{ $attributes->except(['color', 'href'])->class([
        'text-white font-bold py-2 px-4 rounded inline-flex items-center',
        'bg-blue-500 hover:bg-blue-700' => $color === 'blue',
        'bg-red-500 hover:bg-red-700' => $color === 'red',
    ]) }}
>
    {{ $slot }}
</a>
