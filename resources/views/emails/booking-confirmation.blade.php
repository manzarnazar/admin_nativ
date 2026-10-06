<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Booking Confirmation</title>
    <style>
        body { margin: 0; padding: 0; background: #f4f4f5; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        .wrapper { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .header { background: {{ $primaryColor }}; padding: 32px; text-align: center; }
        .header h1 { margin: 0; color: #ffffff; font-size: 22px; font-weight: 700; }
        .header p { margin: 6px 0 0; color: rgba(255,255,255,0.85); font-size: 13px; }
        .badge { display: inline-block; background: #22c55e; color: #fff; font-size: 12px; font-weight: 700; padding: 4px 12px; border-radius: 20px; margin-top: 12px; }
        .header-tagline { margin: 12px 0 0; color: rgba(255,255,255,0.9); font-size: 14px; }
        .body { padding: 32px; background: rgba({{ $primaryColorLightRgb }}, 0.4); }
        .greeting { font-size: 20px; color: #111827; font-weight: 700; margin: 0 0 8px; }
        .intro { font-size: 14px; color: #6b7280; margin: 0 0 24px; }
        .section-title { font-size: 13px; font-weight: 700; color: #374151; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 12px; }
        .detail-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .detail-table td { padding: 12px 0; font-size: 14px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }
        .detail-table td:first-child { color: #374151; width: 55%; }
        .detail-table td:last-child { color: #111827; font-weight: 600; text-align: right; }
        .row-icon { display: inline-block; width: 20px; text-align: center; margin-right: 6px; }
        .combo-row td { width: 50%; color: #374151; font-weight: 400; text-align: left; }
        .combo-row .value { color: #111827; font-weight: 600; margin-left: 4px; }
        .pricing-card { background: #ffffff; border-radius: 8px; padding: 20px 24px 8px; margin-bottom: 24px; }
        .pricing-card .detail-table { margin-bottom: 0; }
        .total-row td { font-size: 18px; font-weight: 800; color: {{ $primaryColor }}; border-bottom: none; padding-top: 14px; }
        .note { background: #ffffff; border-left: 4px solid {{ $primaryColor }}; border-radius: 4px; padding: 14px 16px; font-size: 13px; color: #374151; line-height: 1.6; margin-bottom: 24px; }
        .note p { margin: 0 0 6px; }
        .note p:last-child { margin-bottom: 0; }
        .footer { background: #f8fafc; padding: 24px 32px; text-align: center; border-top: 1px solid #e5e7eb; }
        .footer img { max-height: 32px; margin-bottom: 14px; }
        .footer p { margin: 0; color: #9ca3af; font-size: 12px; line-height: 1.8; }
        .footer a { color: #9ca3af; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>{{ $booking->property?->name ?? 'Hotel Booking' }}</h1>
            <p>Booking #{{ $booking->booking_number }}</p>
            <div><span class="badge">Confirmed</span></div>
            <p class="header-tagline">Your booking is successfully confirmed!</p>
        </div>

        <div class="body">
            <p class="greeting">Hi {{ $booking->guest_name }},</p>
            <p class="intro">Here's a summary of your upcoming stay.</p>

            <p class="section-title">Stay Details</p>
            <table class="detail-table">
                <tr>
                    <td><span class="row-icon">📅</span>Check-in</td>
                    <td>{{ $booking->check_in->format('D, d M Y') }}</td>
                </tr>
                <tr>
                    <td><span class="row-icon">📅</span>Check-out</td>
                    <td>{{ $booking->check_out->format('D, d M Y') }}</td>
                </tr>
                <tr>
                    <td><span class="row-icon">🛏️</span>Room Type</td>
                    <td>{{ $booking->propertyRoom?->roomType?->name ?? '—' }}</td>
                </tr>
                <tr>
                    <td><span class="row-icon">👤</span>Guests</td>
                    <td>{{ $booking->adults }} Adult{{ $booking->adults > 1 ? 's' : '' }}{{ $booking->children > 0 ? ', '.$booking->children.' Child'.($booking->children > 1 ? 'ren' : '') : '' }}</td>
                </tr>
                <tr>
                    <td><span class="row-icon">🏢</span>Property</td>
                    <td>{{ $booking->property?->name ?? '—' }}</td>
                </tr>
                <tr class="combo-row">
                    <td><span class="row-icon">🕐</span>Nights <span class="value">{{ $booking->total_nights }}</span></td>
                    <td><span class="row-icon">🔑</span>Rooms <span class="value">{{ $booking->booked_rooms }}</span></td>
                </tr>
            </table>

            <div class="pricing-card">
                <p class="section-title">Pricing</p>
                <table class="detail-table">
                    <tr>
                        <td>Subtotal</td>
                        <td>{{ $booking->currency_symbol }}{{ number_format((float) $booking->base_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Taxes & Fees</td>
                        <td>{{ $booking->currency_symbol }}{{ number_format((float) $booking->tax_amount, 2) }}</td>
                    </tr>
                    @if ((float) $booking->discount_amount > 0)
                    <tr>
                        <td>Discount</td>
                        <td>− {{ $booking->currency_symbol }}{{ number_format((float) $booking->discount_amount, 2) }}</td>
                    </tr>
                    @endif
                    <tr class="total-row">
                        <td>Total</td>
                        <td>{{ $booking->currency_symbol }}{{ number_format((float) $booking->total_amount, 2) }}</td>
                    </tr>
                </table>
            </div>

            <div class="note">
                <p><span class="row-icon">ℹ️</span><strong>Payment:</strong> Pay at property upon arrival.</p>
                <p><span class="row-icon">⚠️</span>Please carry a valid photo ID at check-in.</p>
            </div>
        </div>

        <div class="footer">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $appName }}" />
            @endif
            <p>
                <strong>Property Name:</strong> {{ $booking->property?->name ?? $appName }}<br>
                <strong>Address:</strong> {{ $booking->property?->street_address ?? '—' }}
                @if ($contactEmail)
                    <br><strong>Contact:</strong> <a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>
                @endif
                <br>This is an automated confirmation. Please do not reply to this email.
            </p>
        </div>
    </div>
</body>
</html>
