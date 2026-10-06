<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Invoice - {{ $booking->booking_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #374151;
            line-height: 1.55;
        }

        .container {
            padding: 28px 40px;
        }

        /* ── Header ── */
        .header {
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }

        .header table {
            width: 100%;
        }

        .company-logo {
            max-height: 48px;
            max-width: 180px;
            margin-bottom: 8px;
            display: block;
        }

        .company-name {
            font-size: 20px;
            font-weight: 700;
            color: #2563eb;
        }

        .company-details {
            font-size: 13px;
            color: #6b7280;
            margin-top: 5px;
            line-height: 1.65;
        }

        .facilitated-property {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #e5e7eb;
        }

        .facilitated-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #6b7280;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .facilitated-name {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
        }

        .facilitated-details {
            font-size: 12px;
            color: #6b7280;
            line-height: 1.5;
            margin-top: 2px;
        }

        .invoice-title {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            text-align: right;
        }

        .invoice-number {
            font-size: 13px;
            color: #2563eb;
            font-weight: 600;
            text-align: right;
            margin-top: 5px;
        }

        .invoice-date {
            font-size: 13px;
            color: #6b7280;
            text-align: right;
            margin-top: 3px;
        }

        /* ── Info Section ── */
        .info-section {
            margin-bottom: 20px;
        }

        .info-section table {
            width: 100%;
        }

        .info-section td {
            vertical-align: top;
            padding: 0;
        }

        .info-block-label {
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin-bottom: 9px;
        }

        .info-block-name {
            font-size: 15px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 4px;
        }

        .info-block-row {
            font-size: 13px;
            color: #374151;
            margin: 4px 0;
        }

        .info-block-key {
            font-weight: 600;
            color: #111827;
        }

        .info-divider {
            border-left: 1px solid #e5e7eb;
            padding-left: 22px;
        }

        /* ── Payment colored text ── */
        .payment-paid {
            color: #15803d;
            font-weight: 500;
        }

        .payment-unpaid {
            color: #92400e;
            font-weight: 500;
        }

        .payment-partial {
            color: #d97706;
            font-weight: 500;
        }

        .payment-refunded {
            color: #6b7280;
            font-weight: 500;
        }

        /* ── Stay Details Table ── */
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .details-table th {
            text-align: left;
            padding: 0 0 9px 0;
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            border-bottom: 2px solid #111827;
        }

        .details-table td {
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
        }

        .details-table .label {
            color: #6b7280;
            width: 22%;
        }

        .details-table .value {
            color: #111827;
            font-weight: 500;
            width: 26%;
            padding-right: 16px;
        }

        .details-table .label-r {
            color: #6b7280;
            width: 22%;
        }

        .details-table .value-r {
            color: #111827;
            font-weight: 500;
            text-align: right;
        }

        /* ── Financial Table ── */
        .financial-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .financial-table thead tr {
            border-bottom: 2px solid #111827;
        }

        .financial-table th {
            text-align: left;
            padding: 0 0 9px 0;
            font-size: 13px;
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 0.7px;
        }

        .financial-table th.th-amount {
            text-align: right;
        }

        .financial-table td {
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 13px;
        }

        .financial-table .amount {
            text-align: right;
            font-weight: 500;
        }

        .financial-table .sub-label {
            display: block;
            font-size: 13px;
            color: #9ca3af;
            margin-top: 2px;
        }

        .financial-table .tax-row td {
            color: #6b7280;
        }

        .financial-table .total-row td {
            padding-top: 10px;
            font-weight: 700;
            font-size: 14px;
            color: #111827;
            border-top: 1px solid #e5e7eb;
            border-bottom: none;
        }

        .financial-table .paid-row td {
            font-size: 13px;
            font-weight: 600;
            border-bottom: none;
        }

        .financial-table .remaining-row td {
            font-size: 13px;
            font-weight: 500;
            color: #d97706;
            border-bottom: none;
        }

        /* ── Footer ── */
        .footer {
            border-top: 1px solid #e5e7eb;
            padding-top: 14px;
            margin-top: 22px;
            text-align: center;
            font-size: 13px;
            color: #9ca3af;
            line-height: 1.7;
        }

        /* ── Watermark ── */
        .watermark {
            position: fixed;
            top: 35%;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 80px;
            color: #dc2626;
            font-weight: 700;
            transform: rotate(-30deg);
            opacity: 0.06;
        }
    </style>
</head>

<body>
    @if ($booking->status->value === 'cancelled')
    <div class="watermark">CANCELLED</div>
    @endif

    <div class="container">
        {{-- Header --}}
        <div class="header">
            <table>
                <tr>
                    <td style="width: 55%;">
                        @if (!empty($platform['logoBase64']))
                        <img src="{{ $platform['logoBase64'] }}" class="company-logo" alt="{{ $platform['name'] ?? 'Logo' }}">
                        @endif

                        @if (!empty($isMulti))
                        {{-- Multi-mode: Platform identity + Property facilitation --}}
                        <div class="company-name">{{ $platform['name'] ?? config('app.name') }}</div>
                        <div class="company-details">
                            @if (!empty($platform['address'])){{ $platform['address'] }}<br>@endif
                            @if (!empty($platform['phone'])){{ $platform['phone'] }}@endif
                            @if (!empty($platform['phone']) && !empty($platform['email'])) &bull; @endif
                            @if (!empty($platform['email'])){{ $platform['email'] }}@endif
                        </div>

                        <div class="facilitated-property">
                            <div class="facilitated-label">Booking at Property</div>
                            <div class="facilitated-name">{{ $property?->name ?? 'Hotel' }}</div>
                            <div class="facilitated-details">
                                @if ($property?->street_address){{ $property->street_address }}<br>@endif
                                {{ collect([$property?->refCity?->name, $property?->refState?->name, $property?->country?->name])->filter()->implode(', ') }}
                                @if ($property?->phone)<br>Phone: {{ $property->phone }}@endif
                                @if ($property?->email)<br>Email: {{ $property->email }}@endif
                            </div>
                        </div>
                        @else
                        {{-- Single-mode: Direct Property identity --}}
                        <div class="company-name">{{ $property?->name ?? $platform['name'] ?? 'Hotel' }}</div>
                        <div class="company-details">
                            @if ($property?->street_address){{ $property->street_address }}<br>@endif
                            {{ collect([$property?->refCity?->name, $property?->refState?->name, $property?->country?->name])->filter()->implode(', ') }}
                            @if ($property?->phone)<br>{{ $property->phone }}@endif
                            @if ($property?->email)<br>{{ $property->email }}@endif
                        </div>
                        @endif
                    </td>
                    <td style="width: 45%; vertical-align: top;">
                        <div class="invoice-title">INVOICE</div>
                        <div class="invoice-number">{{ $booking->booking_number }}</div>
                        <div class="invoice-date">{{ $booking->created_at->format('d M, Y') }}</div>
                    </td>
                </tr>
            </table>
        </div>

        {{-- Guest & Booking Info --}}
        <div class="info-section">
            <table>
                <tr>
                    <td style="width: 47%; vertical-align: top;">
                        <div class="info-block-label">Bill To</div>
                        <div class="info-block-name">{{ $booking->guest_name ?? $customer?->name ?? 'Guest' }}</div>
                        @if ($booking->guest_email || $customer?->email)
                        <div class="info-block-row">{{ $booking->guest_email ?? $customer->email }}</div>
                        @endif
                        @php
                        $displayPhone = $booking->guest_phone
                        ? ($booking->guest_dial_code . ' ' . $booking->guest_phone)
                        : $customer?->phone;
                        @endphp
                        @if ($displayPhone)
                        <div class="info-block-row">{{ $displayPhone }}</div>
                        @endif
                    </td>
                    <td style="width: 6%;"></td>
                    <td style="width: 47%; vertical-align: top; border-left: 1px solid #e5e7eb; padding-left: 28px;">
                        <div class="info-block-label">Booking Details</div>
                        <div class="info-block-row">
                            <span class="info-block-key">Payment</span>&nbsp;&nbsp;<span class="payment-{{ $booking->payment_status->value }}">{{ $booking->payment_status->label() }}</span>
                        </div>
                        @if ($booking->payment_method)
                        <div class="info-block-row">
                            <span class="info-block-key">Method</span>&nbsp;&nbsp;{{ $booking->payment_method->label() }}
                        </div>
                        @endif
                        @if ($booking->transaction_id)
                        <div class="info-block-row">
                            <span class="info-block-key">Txn ID</span>&nbsp;&nbsp;{{ $booking->transaction_id }}
                        </div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        {{-- Stay Details --}}
        <table class="details-table">
            <thead>
                <tr>
                    <th colspan="4">Stay Details</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="label">Check-in</td>
                    <td class="value">{{ $booking->check_in->format('d M, Y') }}</td>
                    <td class="label-r">Check-out</td>
                    <td class="value-r">{{ $booking->check_out->format('d M, Y') }}</td>
                </tr>
                <tr>
                    <td class="label">Duration</td>
                    <td class="value">{{ $booking->total_nights }} Night(s) / {{ $booking->total_nights + 1 }} Day(s)</td>
                    <td class="label-r">Room Type</td>
                    <td class="value-r">{{ $roomType?->name ?? '-' }}</td>
                </tr>
                <tr>
                    <td class="label">Rooms Booked</td>
                    <td class="value">{{ $booking->booked_rooms }}</td>
                    <td class="label-r">Guests</td>
                    <td class="value-r">{{ $booking->adults }} Adult(s){{ $booking->children > 0 ? ', '.$booking->children.' Children' : '' }}</td>
                </tr>
                @php
                $roomDisplay = $assignedRooms ?? $booking->room_number;
                @endphp
                @if ($roomDisplay)
                <tr>
                    <td class="label">{{ ($booking->booked_rooms > 1 && str_contains($roomDisplay, ',')) ? 'Room Numbers' : 'Room Number' }}</td>
                    <td class="value">{{ $roomDisplay }}</td>
                    <td class="label-r"></td>
                    <td class="value-r"></td>
                </tr>
                @endif
            </tbody>
        </table>

        {{-- Financial Summary --}}
        <table class="financial-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="th-amount">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        Room Charges
                        <span class="sub-label">{{ $currency }}{{ number_format((float) ($booking->price_per_night ?? 0), 2) }} &times; {{ $booking->total_nights }} night(s) &times; {{ $booking->booked_rooms }} room(s)</span>
                    </td>
                    <td class="amount">{{ $currency }}{{ number_format((float) $booking->base_amount, 2) }}</td>
                </tr>

                {{-- Tax Breakdown --}}
                @if (count($taxDetails) > 0)
                @foreach ($taxDetails as $tax)
                <tr class="tax-row">
                    <td>
                        {{ $tax['name'] ?? 'Tax' }}
                        @if (($tax['type'] ?? '') === 'percentage')
                        ({{ $tax['rate'] ?? 0 }}%)
                        @endif
                    </td>
                    <td class="amount">{{ $currency }}{{ number_format((float) ($tax['amount'] ?? 0), 2) }}</td>
                </tr>
                @endforeach
                @else
                <tr class="tax-row">
                    <td>Tax &amp; Fees</td>
                    <td class="amount">{{ $currency }}{{ number_format((float) $booking->tax_amount, 2) }}</td>
                </tr>
                @endif

                @if ((float) $booking->discount_amount > 0)
                <tr>
                    <td>Discount</td>
                    <td class="amount" style="color: #dc2626;">-{{ $currency }}{{ number_format((float) $booking->discount_amount, 2) }}</td>
                </tr>
                @endif

                <tr class="total-row">
                    <td>Total Amount</td>
                    <td class="amount">{{ $currency }}{{ number_format((float) $booking->total_amount, 2) }}</td>
                </tr>

                @if ($totalPaid > 0)
                <tr class="paid-row">
                    <td>Amount Paid</td>
                    <td class="amount" style="color: #15803d;">{{ $currency }}{{ number_format($totalPaid, 2) }}</td>
                </tr>
                @endif

                @if ($booking->payment_status === \App\Enums\PaymentStatus::Partial && $totalPaid > 0)
                <tr class="remaining-row">
                    <td>
                        Remaining Amount
                        @if ($booking->property?->advance_percentage)
                        <span class="sub-label">{{ number_format((float) $booking->property->advance_percentage, 0) }}% advance paid</span>
                        @endif
                    </td>
                    <td class="amount">{{ $currency }}{{ number_format((float) $booking->total_amount - $totalPaid, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>

        {{-- Footer --}}
        <div class="footer">
            <p>Thank you for your stay!</p>
            <p>This is a computer-generated invoice and does not require a signature.</p>
            @if (!empty($isMulti))
            @php
            $propertyInfo = collect([
                $property?->name,
                $property?->phone,
                $property?->email,
            ])->filter()->implode(' | ');

            $platformInfo = collect([
                $platform['name'] ?? null,
                $platform['phone'] ?? null,
                $platform['email'] ?? null,
                $platform['website'] ?? null,
            ])->filter()->implode(' | ');
            @endphp
            @if ($propertyInfo)
            <p><strong>Property:</strong> {{ $propertyInfo }}</p>
            @endif
            @if ($platformInfo)
            <p><strong>Platform:</strong> {{ $platformInfo }}</p>
            @endif
            @else
            @php
            $singleInfo = collect([
                $property?->name ?? $platform['name'] ?? null,
                $property?->phone ?? $platform['phone'] ?? null,
                $property?->email ?? $platform['email'] ?? null,
            ])->filter()->implode(' | ');
            @endphp
            @if ($singleInfo)
            <p>{{ $singleInfo }}</p>
            @endif
            @endif
        </div>
    </div>
</body>

</html>