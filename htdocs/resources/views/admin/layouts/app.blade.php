<!DOCTYPE html>
<html lang="tr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Backoffice' }} | {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    @vite(['resources/css/admin.css', 'resources/js/app.js'])
</head>
<body x-data class="h-full bg-canvas font-sans text-ink antialiased">
    <div class="flex h-full min-h-0">
        @include('admin.layouts.sidebar')

        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
            @include('admin.layouts.header')

            <main class="min-h-0 flex-1 overflow-y-auto bg-canvas">
                <div class="p-4 sm:p-6 lg:p-8">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
