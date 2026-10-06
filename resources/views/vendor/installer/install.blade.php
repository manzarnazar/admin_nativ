<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Installation &mdash; {{ config('app.name') }}</title>
    <link href="{{ asset('vendor/installer/styles.css') }}" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            min-height: 100vh;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }
        .installer-card {
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            border: 1px solid #f1f5f9;
            border-radius: 1.25rem;
        }
    </style>
</head>
<body class="min-h-screen h-full w-full flex">
<div class="py-12 sm:px-12 w-full max-w-5xl m-auto">
    <div class="w-full bg-white installer-card">
        <div class="px-4 py-8 border-b border-gray-100 sm:px-8">
            <div class="flex justify-center items-center gap-4">
                <div style="width:48px; height:48px; background:#eff6ff; border: 1px solid #dbeafe; border-radius:12px; display:flex; align-items:center; justify-content:center;">
                    <svg style="width:24px; height:24px; color:#3b82f6; fill:none; stroke:currentColor; stroke-width:2;" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zm0-14v5l3 3"/>
                    </svg>
                </div>
                <h2 class="uppercase tracking-wider font-bold text-2xl text-gray-800">{{ config('app.name') }} Installation</h2>
            </div>
        </div>
        <div class="px-4 py-8 sm:px-8 w-full">
            @yield('step')
        </div>
    </div>
</div>
</body>
</html>
