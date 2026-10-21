<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Backoffice' }} | {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@700;800&family=Manrope:wght@400;500;600;700&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>
<body class="panel-app">
    <script>
        (function () {
            try {
                if (localStorage.getItem('um:sidebar-collapsed') === '1') {
                    document.body.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>
    @include('admin.layouts.header')

    <div class="app-menu-backdrop" id="app-menu-backdrop" hidden></div>
    <div class="app-shell">
        <aside class="um-cell" id="um-cell">
            @include('admin.layouts.sidebar')
        </aside>
        <button type="button" class="um-toggle" id="um-toggle" aria-label="Menüyü kapat" aria-expanded="true" title="Menüyü gizle">
            <i class="fa-solid fa-angles-left um-toggle-icon" aria-hidden="true"></i>
        </button>
        <div class="app-main-cell">
            @include('admin.layouts.partials.alerts')
            @yield('content')
        </div>
    </div>
    @include('admin.layouts.partials.footer')
    @include('admin.layouts.partials.menu')
    @stack('scripts')
</body>
</html>
