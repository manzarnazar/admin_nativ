<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Refund {{ $status === \App\Enums\RefundStatus::Completed ? 'Processed' : 'Update' }}</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: {{ $primaryColor }}; padding: 28px 32px; }
        .header h1 { margin: 0; color: #ffffff; font-size: 20px; font-weight: 700; }
        .header p { margin: 6px 0 0; color: rgba(255,255,255,0.85); font-size: 13px; }
        .badge-success { display: inline-block; background: #22c55e; color: #fff; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; margin-top: 10px; }
        .badge-failed { display: inline-block; background: #ef4444; color: #fff; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; margin-top: 10px; }
        .body { padding: 32px; }
        .greeting { font-size: 16px; color: #111827; font-weight: 600; margin: 0 0 8px; }
        .intro { font-size: 14px; color: #6b7280; margin: 0 0 24px; line-height: 1.6; }
        .section-title { font-size: 13px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 12px; }
        .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .detail-table td { padding: 10px 0; font-size: 14px; border-bottom: 1px solid #f3f4f6; vertical-align: top; }
        .detail-table td:first-child { color: #9ca3af; width: 45%; }
        .detail-table td:last-child { color: #111827; font-weight: 500; text-align: right; }
        .note { border-radius: 4px; padding: 14px 16px; font-size: 13px; color: #374151; line-height: 1.6; margin-bottom: 24px; }
        .note-success { background: #f0fdf4; border-left: 4px solid #22c55e; }
        .note-failed { background: #fef2f2; border-left: 4px solid #ef4444; }
        .footer { background: #f8fafc; padding: 20px 32px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer p { margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.6; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $booking?->property?->name ?? config('app.name') }}</h1>
            <p>Booking #{{ $booking?->booking_number ?? 'N/A' }}</p>
            @if ($status === \App\Enums\RefundStatus::Completed)
                <span class="badge-success">Refund Processed</span>
            @else
                <span class="badge-failed">Refund Failed</span>
            @endif
        </div>

        <div class="body">
            <p class="greeting">Hi {{ $booking?->guest_name ?? 'there' }},</p>

            @if ($status === \App\Enums\RefundStatus::Completed)
                <p class="intro">
                    Great news! Your refund has been successfully processed. The amount will be credited to your original payment method within 5–10 business days, depending on your bank.
                </p>
            @else
                <p class="intro">
                    We were unable to process your refund automatically. Please don't worry — you can submit a manual refund request from your booking details, and our team will transfer the amount directly to your bank account.
                </p>
            @endif

            <p class="section-title">Refund Details</p>
            <table class="detail-table">
                <tr>
                    <td>Booking Number</td>
                    <td>#{{ $booking?->booking_number ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Property</td>
                    <td>{{ $booking?->property?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td>Refund Amount</td>
                    <td>{{ $booking?->currency_symbol ?? '₹' }}{{ number_format((float) $refund->amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Refund Percentage</td>
                    <td>{{ (int) $refund->refund_percentage }}%</td>
                </tr>
                <tr>
                    <td>Status</td>
                    <td>{{ $status === \App\Enums\RefundStatus::Completed ? 'Completed' : 'Failed' }}</td>
                </tr>
                @if ($refund->refund_id)
                <tr>
                    <td>Reference ID</td>
                    <td>{{ $refund->refund_id }}</td>
                </tr>
                @endif
            </table>

            @if ($status === \App\Enums\RefundStatus::Completed)
                <div class="note note-success">
                    <strong>Note:</strong> If you paid via credit/debit card, the refund may take 5–10 business days to reflect in your statement. UPI and wallet refunds are usually faster.
                </div>
            @else
                <div class="note note-failed">
                    <strong>What to do next:</strong> Open your booking details in the app and tap "Request Manual Refund". Submit your bank details, and our team will process the transfer within 3–5 business days.
                </div>
            @endif
        </div>

        <div class="footer">
            <p>
                {{ $booking?->property?->name ?? config('app.name') }}<br>
                This is an automated notification. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
