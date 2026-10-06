<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $marketingMessage->title }}</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: {{ $primaryColor }}; padding: 28px 32px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; }
        .image-wrap img { width: 100%; display: block; }
        .body { padding: 32px; }
        .body p { margin: 0 0 20px; color: #374151; font-size: 15px; line-height: 1.7; }
        .cta-wrap { text-align: center; margin: 28px 0 8px; }
        .cta-btn { display: inline-block; background: {{ $primaryColor }}; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-size: 15px; font-weight: 600; }
        .footer { background: #f8fafc; padding: 20px 32px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { margin: 0; color: #9ca3af; font-size: 12px; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $marketingMessage->title }}</h1>
        </div>

        @if ($marketingMessage->image)
            <div class="image-wrap">
                <img src="{{ asset('storage/' . $marketingMessage->image) }}" alt="{{ $marketingMessage->title }}" />
            </div>
        @endif

        <div class="body">
            <p>{!! nl2br(e($marketingMessage->body)) !!}</p>

            @if ($clickTrackingUrl)
                <div class="cta-wrap">
                    <a href="{{ $clickTrackingUrl }}" class="cta-btn" style="display: inline-block; background: {{ $primaryColor }}; color: #ffffff; text-decoration: none; padding: 12px 32px; border-radius: 8px; font-size: 15px; font-weight: 600;">Learn More</a>
                </div>
            @endif
        </div>

        <div class="footer">
            <p>You are receiving this because you are a registered customer.</p>
        </div>
    </div>

    {{-- Open tracking pixel --}}
    <img src="{{ $openTrackingUrl }}" width="1" height="1" style="display:none;" alt="" />
</body>
</html>
