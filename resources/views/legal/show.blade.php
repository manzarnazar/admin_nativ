<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $policy->meta_title ?: $title }} — {{ config('app.name') }}</title>
    @if ($policy->meta_description)
        <meta name="description" content="{{ $policy->meta_description }}">
    @endif
    @if ($policy->meta_keyword)
        <meta name="keywords" content="{{ $policy->meta_keyword }}">
    @endif
    @if ($policy->og_image)
        <meta property="og:image" content="{{ asset('storage/' . $policy->og_image) }}">
    @endif
    @if ($policy->schema_markup)
        <script type="application/ld+json">{!! $policy->schema_markup !!}</script>
    @endif
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.7;
            color: #1a1a1a;
            background: #f9f9f9;
        }
        .page-header {
            background: #ffffff;
            border-bottom: 1px solid #e5e7eb;
            padding: 24px 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 24px;
        }
        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0 0 8px;
            color: #111827;
        }
        .last-updated {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
        }
        .content-wrapper {
            padding: 40px 0 80px;
        }
        .hero-banner {
            max-width: 800px;
            margin: 24px auto 0;
            padding: 0 24px;
        }
        .hero-banner img {
            width: 100%;
            height: auto;
            max-height: 320px;
            object-fit: cover;
            border-radius: 8px;
            display: block;
        }
        .section {
            background: #ffffff;
            border-radius: 8px;
            border: 1px solid #e5e7eb;
            padding: 32px;
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #111827;
            margin: 0 0 16px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f3f4f6;
        }
        .section-content {
            color: #374151;
            line-height: 1.8;
        }
        .section-content p { margin: 0 0 12px; }
        .section-content p:last-child { margin-bottom: 0; }
        .section-content ul, .section-content ol { margin: 8px 0 12px 24px; }
        .section-content li { margin-bottom: 6px; }
        .section-content h1, .section-content h2, .section-content h3,
        .section-content h4, .section-content h5, .section-content h6 {
            margin: 20px 0 10px;
            color: #111827;
        }
        .section-content a { color: #2563eb; }
        .section-content a:hover { text-decoration: underline; }
        .section-content strong { color: #111827; }
        .section-content table { width: 100%; border-collapse: collapse; margin: 12px 0; }
        .section-content th, .section-content td {
            border: 1px solid #e5e7eb;
            padding: 8px 12px;
            text-align: left;
        }
        .section-content th { background: #f9fafb; font-weight: 600; }
    </style>
</head>
<body>
    <header class="page-header">
        <div class="container">
            <h1 class="page-title">{{ $title }}</h1>
            <p class="last-updated">Last updated: {{ $policy->updated_at->format('F j, Y') }}</p>
        </div>
    </header>

    @if ($policy->og_image)
        <div class="hero-banner">
            <img src="{{ asset('storage/' . $policy->og_image) }}" alt="{{ $title }}">
        </div>
    @endif

    <main class="content-wrapper">
        <div class="container">
            @foreach ($policy->sections as $section)
                <div class="section">
                    @if (!empty($section['title']))
                        <h2 class="section-title">{{ $section['title'] }}</h2>
                    @endif
                    <div class="section-content">
                        {!! $section['content'] !!}
                    </div>
                </div>
            @endforeach
        </div>
    </main>
</body>
</html>
