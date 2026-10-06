<?php

use App\Http\Controllers\PartnerApi\PartnerAuthController;
use Illuminate\Support\Facades\Route;

// Partner Auth — public (no token needed)
Route::prefix('partner/auth')->group(function () {
    Route::post('/register', [PartnerAuthController::class, 'register']);
});
