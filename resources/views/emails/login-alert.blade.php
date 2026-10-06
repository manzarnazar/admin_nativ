<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>New sign-in to your eStay account</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: {{ $primaryColor }}; padding: 28px 32px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; }
        .header p { margin: 6px 0 0; color: rgba(255,255,255,0.85); font-size: 13px; }
        .body { padding: 32px; }
        .greeting { font-size: 16px; color: #111827; font-weight: 600; margin: 0 0 8px; }
        .intro { font-size: 14px; color: #6b7280; margin: 0 0 24px; line-height: 1.6; }
        .section-title { font-size: 13px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 12px; }
        .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .detail-table td { padding: 10px 0; font-size: 14px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .detail-table td:first-child { color: #9ca3af; width: 35%; }
        .detail-table td:last-child { color: #111827; font-weight: 500; }
        .alert-box { background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px; padding: 14px 16px; font-size: 13px; color: #78350f; line-height: 1.6; margin-bottom: 8px; }
        .alert-box strong { color: #78350f; }
        .footer { background: #f8fafc; padding: 20px 32px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>New sign-in to your account</h1>
            <p>Security notification</p>
        </div>

        <div class="body">
            <p class="greeting">Hi {{ $name }},</p>
            <p class="intro">We noticed a new sign-in to your eStay account. If this was you, you can safely ignore this email.</p>

            <p class="section-title">Sign-in details</p>
            <table class="detail-table">
                <tr>
                    <td>Method</td>
                    <td>{{ $method }}</td>
                </tr>
                <tr>
                    <td>Time</td>
                    <td>{{ $loggedInAt->format('D, j M Y \a\t H:i') }} UTC</td>
                </tr>
            </table>

            <div class="alert-box">
                <strong>Wasn't you?</strong> If you don't recognise this sign-in, your account may have been compromised. Change your password immediately and contact our support team.
            </div>
        </div>

        <div class="footer">
            <p>This is an automated security alert from eStay.</p>
            <p>You are receiving this because someone signed in to the account associated with this email address.</p>
        </div>
    </div>
</body>
</html>
