@php
    $userName = Auth::user()->name;
    $userInitials = collect(preg_split('/\s+/u', trim($userName)))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp
<header class="app-topbar">
    <button type="button" class="app-menu-btn" id="app-menu-btn" aria-label="Menüyü aç" aria-expanded="false">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <a href="{{ route('admin.dashboard') }}" class="app-topbar-brand" title="HafLine ana sayfa">
        <img src="{{ asset('img/logo.png') }}" alt="HafLine" class="app-brand-logo">
        <span class="app-brand-tag">
            <span class="app-brand-tag-title">Hafriyat Yönetim</span>
            <span class="app-brand-tag-sub">Kontrol Paneli</span>
        </span>
    </a>
    <div class="app-topbar-right">
        <span class="app-topbar-user">
            <span class="app-user-avatar" aria-hidden="true">{{ $userInitials ?: '?' }}</span>
            <span class="app-user-text">
                <span class="app-user-kicker">Merhaba</span>
                <span class="app-user-name">{{ $userName }}</span>
            </span>
        </span>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="app-topbar-exit" title="Güvenli çıkış" aria-label="Güvenli çıkış">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</header>
