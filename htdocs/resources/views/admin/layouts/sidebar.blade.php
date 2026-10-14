<div class="um-nav" id="um-nav">
    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.dashboard')]) id="um-sec-home">
        <button type="button" class="um-title" data-um-toggle="um-sec-home">
            <span class="um-title-text">
                <i class="fa-solid fa-gauge um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Genel</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.dashboard') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.dashboard')])>Gösterge paneli</a>
        </div>
    </div>

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.payment-types.*', 'admin.cash-categories.*', 'admin.banks.*')]) id="um-sec-definitions">
        <button type="button" class="um-title" data-um-toggle="um-sec-definitions">
            <span class="um-title-text">
                <i class="fa-solid fa-sliders um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Tanımlar</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.payment-types.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.payment-types.*')])>Ödeme tipleri</a>
            <a href="{{ route('admin.cash-categories.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.cash-categories.*')])>Kasa kategorileri</a>
            <a href="{{ route('admin.banks.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.banks.*')])>Bankalar</a>
        </div>
    </div>

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.vehicles.*', 'admin.vehicle-owners.*', 'admin.vehicle-classes.*')]) id="um-sec-fleet">
        <button type="button" class="um-title" data-um-toggle="um-sec-fleet">
            <span class="um-title-text">
                <i class="fa-solid fa-truck um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Filo</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.vehicles.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.vehicles.*')])>Araçlar</a>
            <a href="{{ route('admin.vehicle-owners.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.vehicle-owners.*')])>Araç sahipleri</a>
            <a href="{{ route('admin.vehicle-classes.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.vehicle-classes.*')])>Araç sınıfları</a>
        </div>
    </div>

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.sites.*', 'admin.dump-sites.*')]) id="um-sec-field">
        <button type="button" class="um-title" data-um-toggle="um-sec-field">
            <span class="um-title-text">
                <i class="fa-solid fa-map-location-dot um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Saha</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.sites.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.sites.*')])>Şantiyeler</a>
            <a href="{{ route('admin.dump-sites.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.dump-sites.*')])>Döküm yerleri</a>
        </div>
    </div>

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.examples.*')]) id="um-sec-examples">
        <button type="button" class="um-title" data-um-toggle="um-sec-examples">
            <span class="um-title-text">
                <i class="fa-solid fa-layer-group um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Örnek kayıtlar</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.examples.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.examples.*')])>Kayıt listesi</a>
        </div>
    </div>

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.hauls.*')]) id="um-sec-hauls">
        <button type="button" class="um-title" data-um-toggle="um-sec-hauls">
            <span class="um-title-text">
                <i class="fa-solid fa-truck-ramp-box um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Taşıma fişleri</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.hauls.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.hauls.*')])>Fiş listesi</a>
        </div>
    </div>
</div>
@include('admin.layouts.partials.menu')
