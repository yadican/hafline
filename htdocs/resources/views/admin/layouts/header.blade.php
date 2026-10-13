<div class="app-topbar">
    <button type="button" class="app-menu-btn" id="app-menu-btn" aria-label="Menüyü aç" aria-expanded="false">
        <i class="fa-solid fa-bars" aria-hidden="true"></i>
    </button>
    <a href="{{ route('admin.dashboard') }}" class="app-topbar-brand" title="HafLine ana sayfa">
        <img src="{{ asset('img/logo.png') }}" alt="HafLine" class="app-brand-logo">
        <span class="app-brand-tag">Hafriyat Yönetim</span>
    </a>
    <div class="app-topbar-right">
        <span class="app-topbar-user">
            <span class="app-user-avatar" aria-hidden="true"><i class="fa-solid fa-user"></i></span>
            <span class="app-user-text">
                <span class="app-user-kicker">Merhaba</span>
                <span class="app-user-name">{{ Auth::user()->name }}</span>
            </span>
        </span>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="app-topbar-exit" title="Güvenli çıkış" aria-label="Güvenli çıkış">
                <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            </button>
        </form>
    </div>
</div>
