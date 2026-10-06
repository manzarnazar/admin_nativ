<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Welcome to {{ $appName }}</title>
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

        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0 0 12px;
        }

        .credentials-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
            background: #f8fafc;
            border-radius: 8px;
            padding: 4px;
        }

        .credentials-table td {
            padding: 14px 16px;
            font-size: 14px;
            vertical-align: top;
        }

        .credentials-table td:first-child {
            color: #6b7280;
            width: 35%;
        }

        .credentials-table td:last-child {
            color: #111827;
            font-weight: 600;
            font-family: 'SFMono-Regular', Consolas, monospace;
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
            background: #fef9c3;
            border-left: 4px solid #ca8a04;
            border-radius: 4px;
            padding: 14px 16px;
            font-size: 13px;
            color: #713f12;
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
            <h1>Welcome to {{ $appName }}</h1>
            <p>Your account has been created.</p>
        </div>

        <div class="body">
            <p class="greeting">Hello {{ $user->name }},</p>
            <p class="intro">
                An account has been created for you so you can manage your bookings online.
                Use the credentials below to log in.
            </p>

            <p class="section-title">Your Login Details</p>
            <table class="credentials-table">
                <tr>
                    <td>Email</td>
                    <td>{{ $user->email }}</td>
                </tr>
                <tr>
                    <td>Password</td>
                    <td>{{ $plainPassword }}</td>
                </tr>
            </table>

            <a href="{{ $loginUrl }}" class="cta" style="display: inline-block; background: {{ $primaryColor }}; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-size: 14px; font-weight: 600;">Log in to your account</a>

            <div class="note">
                For your security, please change this password after your first login from your profile settings.
            </div>

            <p class="intro" style="margin-top: 16px;">
                If you didn't expect this email, you can safely ignore it.
            </p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ $appName }}. All rights reserved.</p>
        </div>
    </div>
</body>

</html>