<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Flutterwave payment callback - Flutterwave redirects here after payment completion.
     * Maps status to frontend status and redirects.
     */
    public function flutterwaveCallback(Request $request, Booking $booking)
    {
        $status = $request->query('status');

        // Determine frontend status based on Flutterwave status
        // Flutterwave sends 'successful' for success, other values for failure
        $frontendStatus = ($status === 'successful') ? 'success' : 'failed';

        $frontendBase = rtrim(Setting::get('frontend_web_url') ?: env('FRONTEND_URL'), '/');

        // Redirect to frontend with status and booking ID
        return redirect()->away($frontendBase.'/confirm-booking?status='.$frontendStatus.'&booking_id='.$booking->id);
    }

    /**
     * Razorpay payment link callback - Razorpay redirects here after payment completion.
     * Checks payment status and redirects to frontend with appropriate status.
     */
    public function razorpayCallback(Request $request, Booking $booking)
    {
        $status = $request->query('razorpay_payment_link_status');

        // Determine frontend status based on Razorpay payment link status
        $frontendStatus = ($status === 'paid') ? 'success' : 'failed';

        $frontendBase = rtrim(Setting::get('frontend_web_url') ?: env('FRONTEND_URL'), '/');

        // Redirect to frontend with status and booking ID
        return redirect()->away($frontendBase.'/confirm-booking?status='.$frontendStatus.'&booking_id='.$booking->id);
    }
}
