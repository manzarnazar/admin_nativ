<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ __('admin.partner_approved_email_subject', ['app' => $appName]) }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background: #f4f4f5;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }

        .wrapper {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .header {
            background: {{ $primaryColor }};
            padding: 28px 32px;
        }

        .header h1 {
            margin: 0;
            color: #ffffff;
            font-size: 20px;
            font-weight: 700;
        }

        .header p {
            margin: 6px 0 0;
            color: rgba(255,255,255,0.85);
            font-size: 13px;
        }

        .body {
            padding: 32px;
        }

        .greeting {
            font-size: 16px;
            color: #111827;
            font-weight: 600;
            margin: 0 0 8px;
        }

        .intro {
            font-size: 14px;
            color: #6b7280;
            margin: 0 0 24px;
            line-height: 1.6;
        }

        .success-badge {
            display: inline-block;
            background: #dcfce7;
            color: #15803d;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 20px;
            margin-bottom: 24px;
        }

        .cta {
            display: inline-block;
            background: {{ $primaryColor }};
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            margin: 8px 0 24px;
        }

        .note {
            background: #f0fdf4;
            border-left: 4px solid #16a34a;
            border-radius: 4px;
            padding: 14px 16px;
            font-size: 13px;
            color: #14532d;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .footer {
            background: #f8fafc;
            padding: 20px 32px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer p {
            margin: 0;
            color: #9ca3af;
            font-size: 12px;
            line-height: 1.6;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $appName }}</h1>
            <p>{{ __('admin.partner_approved_email_subheading') }}</p>
        </div>

        <div class="body">
            <p class="greeting">{{ __('admin.hello') }}, {{ $partner->user->name }},</p>

            <span class="success-badge">✓ {{ __('admin.account_approved') }}</span>

            <p class="intro">
                {{ __('admin.partner_approved_email_body', ['app' => $appName]) }}
            </p>

            <a href="{{ $loginUrl }}" class="cta" style="display: inline-block; background: {{ $primaryColor }}; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 600;">{{ __('admin.go_to_partner_panel') }}</a>

            <div class="note">
                {{ __('admin.partner_approved_email_note') }}
            </div>

            <p class="intro" style="margin-top: 16px;">
                {{ __('admin.partner_approved_email_closing') }}
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $appName }}. {{ __('admin.all_rights_reserved') }}</p>
        </div>
    </div>
</body>

</html>
