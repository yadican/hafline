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

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.hauls.*')]) id="um-sec-tickets">
        <button type="button" class="um-title" data-um-toggle="um-sec-tickets">
            <span class="um-title-text">
                <i class="fa-solid fa-file-invoice um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Fiş işlemleri</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.hauls.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.hauls.*')])>Taşıma fişleri</a>
        </div>
    </div>

    @php
        $partyType = request()->route('party') instanceof \App\Models\Party
            ? request()->route('party')->type
            : \App\Enums\PartyType::tryFrom((string) request('type'));
        $onParty = request()->routeIs('admin.parties.*');
    @endphp
    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.parties.*', 'admin.persons.*', 'admin.companies.*')]) id="um-sec-accounts">
        <button type="button" class="um-title" data-um-toggle="um-sec-accounts">
            <span class="um-title-text">
                <i class="fa-solid fa-address-card um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Cari</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.persons.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.persons.*') || ($onParty && $partyType === \App\Enums\PartyType::Person)])>Kişiler</a>
            <a href="{{ route('admin.companies.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.companies.*') || ($onParty && $partyType === \App\Enums\PartyType::Company)])>Şirketler</a>
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

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.vehicles.*', 'admin.vehicle-classes.*')]) id="um-sec-fleet">
        <button type="button" class="um-title" data-um-toggle="um-sec-fleet">
            <span class="um-title-text">
                <i class="fa-solid fa-truck um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Filo</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.vehicles.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.vehicles.*')])>Araçlar</a>            <a href="{{ route('admin.vehicle-classes.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.vehicle-classes.*')])>Araç sınıfları</a>
        </div>
    </div>

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.sites.*', 'admin.dump-sites.*', 'admin.haul-routes.*', 'admin.fuel-stations.*')]) id="um-sec-field">
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
            <a href="{{ route('admin.haul-routes.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.haul-routes.*')])>Güzergahlar ve fiyatlar</a>
            <a href="{{ route('admin.fuel-stations.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.fuel-stations.*')])>Akaryakıt istasyonları</a>
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
</div>
@include('admin.layouts.partials.menu')
