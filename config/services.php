<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'reviews' => [
        'max_images' => env('REVIEW_MAX_IMAGES', 5),
    ],

    'rooms' => [
        'min_images' => env('ROOM_MIN_IMAGES', 8),
        'max_images' => env('ROOM_MAX_IMAGES', 8),
        'max_image_size_mb' => env('ROOM_MAX_IMAGE_SIZE_MB', 5),
    ],

    'properties' => [
        // Bumped from 2 to 3 — the client-side canvas resize can noticeably inflate PNGs
        // beyond their original size, so a hard 2MB cutoff was rejecting legitimate photos.
        // Real fix (server-side re-compression via GD) deferred since it'd change the
        // stored file extension (png -> jpg) — revisit this value once that's in place.
        'max_image_size_mb' => env('PROPERTY_MAX_IMAGE_SIZE_MB', 3),
    ],

    'frontend' => [
        'url' => env('FRONTEND_URL', ''),
    ],

];
