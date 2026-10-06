<?php

namespace App\Services;

use App\Enums\PaymentTransactionStatus;
use App\Models\Booking;
use App\Models\Setting;
use App\Support\SystemMode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Generate a PDF invoice for a booking and return as download response.
     */
    public function download(Booking $booking): Response
    {
        $data = $this->buildViewData($booking);

        $pdf = Pdf::loadView('invoices.booking-invoice', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->download('Invoice-'.$booking->booking_number.'.pdf');
    }

    /**
     * Build the view data required for the invoice template.
     */
    public function buildViewData(Booking $booking): array
    {
        $booking->load([
            'property.refCity',
            'property.refState',
            'property.country',
            'propertyRoom.roomType',
            'customer',
            'bookedByAdmin',
            'payments',
            'roomAssignments.room',
        ]);

        $currencySymbol = $booking->currency_symbol ?? $booking->property?->country?->currency_symbol ?? '$';
        $currencyCode = $booking->currency_code ?? $booking->property?->country?->currency_code ?? '';
        $currency = $this->toPdfCurrency($currencySymbol, $currencyCode);

        $successfulPayments = $booking->payments->filter(fn ($p) => $p->status === PaymentTransactionStatus::Success);

        $logoSetting = Setting::get('logo');
        $logoPath = is_array($logoSetting) ? ($logoSetting[0] ?? null) : $logoSetting;
        $logoBase64 = null;
        if ($logoPath && Storage::disk('public')->exists($logoPath)) {
            $fullPath = Storage::disk('public')->path($logoPath);
            $mime = @mime_content_type($fullPath) ?: 'image/png';
            $logoBase64 = 'data:'.$mime.';base64,'.base64_encode(file_get_contents($fullPath));
        }

        $platform = [
            'name' => Setting::get('app_name', config('app.name')),
            'email' => Setting::get('contact_email'),
            'phone' => Setting::get('contact_phone'),
            'address' => Setting::get('contact_address'),
            'website' => Setting::get('frontend_web_url'),
            'logoBase64' => $logoBase64,
        ];

        $assignedRooms = $booking->roomAssignments
            ->map(fn ($assignment) => $assignment->room?->room_number)
            ->filter()
            ->values()
            ->implode(', ');

        return [
            'booking' => $booking,
            'property' => $booking->property,
            'roomType' => $booking->propertyRoom?->roomType,
            'customer' => $booking->customer,
            'currency' => $currency,
            'taxDetails' => $booking->tax_details ?? [],
            'totalPaid' => (float) $successfulPayments->sum('amount'),
            'isMulti' => SystemMode::isMulti(),
            'platform' => $platform,
            'assignedRooms' => $assignedRooms ?: $booking->room_number,
        ];
    }

    /**
     * Convert a currency symbol to a DomPDF-safe ASCII equivalent.
     *
     * DomPDF's built-in fonts cover Latin-1 (U+0000–U+00FF) and the Euro sign (U+20AC).
     * Arabic script, Devanagari, and Unicode currency symbols like ₹ or ₦ render as '?'.
     * Known symbols are mapped explicitly; anything else outside Latin-1+€ falls back to
     * the ISO currency code (e.g. "AED", "SAR").
     */
    private function toPdfCurrency(string $symbol, string $code): string
    {
        // Trailing space included so "AED 80.00" renders correctly when concatenated with the amount.
        // Single-char symbols like $, £, € are passed through as-is (no space needed for those).
        $map = [
            '₹' => 'Rs. ',  // Indian Rupee
            'INR' => 'Rs. ',
            'Rs' => 'Rs. ',
            'د.إ' => 'AED ',  // UAE Dirham
            '﷼' => 'SAR ',  // Saudi / Qatari / Iranian Riyal
            'ر.ع.' => 'OMR ',  // Omani Rial
            '₦' => 'NGN ',  // Nigerian Naira
            '₱' => 'PHP ',  // Philippine Peso
            '₩' => 'KRW ',  // South Korean Won
            '₫' => 'VND ',  // Vietnamese Dong
            '฿' => 'THB ',  // Thai Baht
            '₺' => 'TRY ',  // Turkish Lira
            '₽' => 'RUB ',  // Russian Ruble
        ];

        if (isset($map[$symbol])) {
            return $map[$symbol];
        }

        // Detect any character outside Latin-1 + € (U+20AC) — not renderable by DomPDF.
        if (preg_match('/[^\x20-\x7E\xA0-\xFF\x{20AC}]/u', $symbol)) {
            return $code ? $code.' ' : $symbol;
        }

        return $symbol;
    }

    /**
     * Generate PDF and return as raw content (for streaming).
     */
    public function generatePdf(Booking $booking): string
    {
        $data = $this->buildViewData($booking);

        $pdf = Pdf::loadView('invoices.booking-invoice', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }
}
