<?php

namespace App\Http\Controllers;

use App\Models\MarketingMessage;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class MarketingTrackingController extends Controller
{
    /**
     * Track email open via 1×1 transparent pixel.
     */
    public function open(string $uuid): Response
    {
        $message = MarketingMessage::where('tracking_uuid', $uuid)->first();

        if ($message) {
            $message->increment('open_count');
            // Recalculate open rate percentage
            if ($message->sent_to > 0) {
                $message->open_rate = round(($message->open_count / $message->sent_to) * 100, 2);
                $message->save();
            }
        }

        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200)
            ->header('Content-Type', 'image/gif')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Track link click and redirect to the original URL.
     */
    public function click(string $uuid): RedirectResponse
    {
        $message = MarketingMessage::where('tracking_uuid', $uuid)->first();

        if (! $message) {
            abort(404);
        }

        $message->increment('click_count');
        $message->clicks = $message->click_count;
        $message->save();

        return redirect()->away($message->redirect_url ?? '/');
    }
}
