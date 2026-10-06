<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Services\InvoiceService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class InvoiceController extends Controller
{
    public function download(Booking $booking): Response
    {
        Gate::authorize('view', $booking);

        return app(InvoiceService::class)->download($booking);
    }
}
