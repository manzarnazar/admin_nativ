<?php

use App\Http\Controllers\Api\AboutController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BannerController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CurrencyController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventInquiryController;
use App\Http\Controllers\Api\FacilityController;
use App\Http\Controllers\Api\FavoriteController;
use App\Http\Controllers\Api\HelpSupportController;
use App\Http\Controllers\Api\HomepageController;
use App\Http\Controllers\Api\LegalPolicyController;
use App\Http\Controllers\Api\ManualRefundController;
use App\Http\Controllers\Api\MapsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OfferController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SeoSettingController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UserQueryController;
use App\Http\Middleware\EnsureMultiMode;
use Illuminate\Support\Facades\Route;

// Public routes (no auth needed)
Route::get('/settings', [SettingsController::class, 'index']);
Route::get('/translations', [SettingsController::class, 'translations']);
Route::get('/language', [SettingsController::class, 'language']);
Route::get('/about', [AboutController::class, 'index']);
// Route::get('/homepage', [HomepageController::class, 'index']); // hidden — replaced by /homepage/sections
Route::get('/homepage/sections', [HomepageController::class, 'sections']);
Route::get('/homepage/sections/{id}/properties', [HomepageController::class, 'sectionProperties']);
Route::get('/help-support', [HelpSupportController::class, 'index']);
Route::get('/countries', [CountryController::class, 'index']);
Route::get('/currencies', [CurrencyController::class, 'index']);
Route::get('/banners', [BannerController::class, 'index']);
Route::get('/facilities', [FacilityController::class, 'index']);
Route::get('/reviews', [ReviewController::class, 'index']);
Route::get('/events', [EventController::class, 'index']);
Route::post('/event-inquiries', [EventInquiryController::class, 'store']);
Route::get('/properties', [PropertyController::class, 'index']);
Route::get('/properties/rule-filters', [PropertyController::class, 'ruleFilters']);
Route::get('/properties/dropdown', [PropertyController::class, 'dropdown']);
Route::get('/search/suggest', [SearchController::class, 'suggest']);
Route::get('/properties/rooms', [PropertyController::class, 'rooms']);
Route::get('/property-details', [PropertyController::class, 'show']); // New: /show?slug=...
Route::get('/properties/{slug}', [PropertyController::class, 'showBySlug']); // Legacy: /{slug}
Route::get('/properties/{slug}/nearby-places', [PropertyController::class, 'nearbyPlaces']);

// Maps
Route::prefix('maps')->group(function () {
    Route::get('/nearby-places', [MapsController::class, 'nearbyPlaces']);
    Route::get('/categories', [MapsController::class, 'categories']);
    Route::get('/debug/overpass', [MapsController::class, 'debugOverpass']);
});

// Blogs (public)
Route::get('/blogs', [BlogController::class, 'index']);
Route::get('/blogs/{slug}', [BlogController::class, 'show']);
Route::get('/blog-categories', [BlogController::class, 'categories']);

// Legal Policies (public)
Route::get('/legal-policies', [LegalPolicyController::class, 'index']);

// User Queries (public)
Route::post('/queries', [UserQueryController::class, 'store']);

Route::post('/auth/send-email-otp', [AuthController::class, 'sendEmailOtp']);
Route::post('/auth/otp/verify', [AuthController::class, 'verifyOtp']);
Route::post('/auth/register/complete', [AuthController::class, 'registerComplete']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/social/login', [AuthController::class, 'socialLogin']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

// Protected routes (Bearer token required)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);
    Route::post('/auth/profile', [AuthController::class, 'updateProfile'])->middleware('demo.block');
    Route::post('/auth/change-password', [AuthController::class, 'changePassword'])->middleware('demo.block');
    Route::post('/auth/delete-account', [AuthController::class, 'deleteAccount'])->middleware('demo.block');

    // Reviews
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/reviews', [ReviewController::class, 'update']);

    // Favorites (multi-mode only for now)
    Route::middleware(EnsureMultiMode::class)->group(function () {
        Route::post('/favorites/{slug}', [FavoriteController::class, 'store']);
        Route::delete('/favorites/{slug}', [FavoriteController::class, 'destroy']);
        Route::get('/favorites', [FavoriteController::class, 'index']);
    });

    // Coupons
    Route::post('/coupons/validate', [CouponController::class, 'validateCode']);
    Route::get('/offers', [OfferController::class, 'index']);

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::get('/bookings/{bookingNumber}', [BookingController::class, 'show']);
    Route::get('/bookings/{bookingNumber}/invoice', [BookingController::class, 'downloadInvoice']);
    Route::get('/bookings/{bookingNumber}/cancel-preview', [BookingController::class, 'cancelPreview']);
    Route::post('/bookings/{bookingNumber}/cancel', [BookingController::class, 'cancel']);
    Route::post('/bookings/quote', [BookingController::class, 'quote']);
    Route::post('/bookings/lock', [BookingController::class, 'lock']);
    Route::post('/bookings/confirm', [BookingController::class, 'confirm']);
    Route::post('/bookings/create-with-payment', [BookingController::class, 'createWithPayment']);
    Route::post('/bookings/{bookingNumber}/retry-payment', [BookingController::class, 'retryPayment']);

    // Manual Refund Requests
    Route::post('/bookings/{bookingNumber}/manual-refund', [ManualRefundController::class, 'store']);

    // Payments
    Route::post('/payments/{id}/retry', [PaymentController::class, 'retry']);
    Route::get('/transactions', [TransactionController::class, 'index']);

    Route::post('/auth/fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::get('/auth/get-notification-preferences', [AuthController::class, 'getNotificationPreferences']);
    Route::post('/auth/update-notification-preferences', [AuthController::class, 'updateNotificationPreferences']);

    // Notification
    Route::get('/get-notifications', [NotificationController::class, 'getNotification']);
    Route::get('/get-notification-preferences', [NotificationController::class, 'getPreferences']);
    Route::post('/update-notification-preferences', [NotificationController::class, 'updatePreference']);
});

// Seo Settings
Route::get('/get-seo-pages', [SeoSettingController::class, 'index']);
// Webhook routes (public, no auth - gateways need access)
Route::post('/payments/webhook/{gateway}', [PaymentController::class, 'webhook']);
Route::post('/refunds/webhook/{gateway}', [PaymentController::class, 'refundWebhook']);
