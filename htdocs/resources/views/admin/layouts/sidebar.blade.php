<div class="um-nav" id="um-nav">
    <div class="um-section" id="um-sec-home">
        <a href="{{ route('admin.dashboard') }}" @class(['um-title', 'um-active' => request()->routeIs('admin.dashboard')])>
            <span class="um-title-text">
                <i class="fa-solid fa-gauge um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Gösterge paneli</span>
            </span>
        </a>
    </div>

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.hauls.*', 'admin.machine-works.*', 'admin.fuel-receipts.*')]) id="um-sec-tickets">
        <button type="button" class="um-title" data-um-toggle="um-sec-tickets">
            <span class="um-title-text">
                <i class="fa-solid fa-file-invoice um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Fiş işlemleri</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.hauls.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.hauls.*')])>Taşıma fişleri</a>
            <a href="{{ route('admin.machine-works.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.machine-works.*')])>Makine çalışma fişleri</a>
            <a href="{{ route('admin.fuel-receipts.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.fuel-receipts.*')])>Yakıt fişleri</a>
        </div>
    </div>

    @php
        $partyType = request()->route('party') instanceof \App\Models\Party
            ? request()->route('party')->type
            : \App\Enums\PartyType::tryFrom((string) request('type'));
        $onParty = request()->routeIs('admin.parties.*');
    @endphp
    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.parties.*', 'admin.persons.*', 'admin.companies.*', 'admin.cari-entries.*')]) id="um-sec-accounts">
        <button type="button" class="um-title" data-um-toggle="um-sec-accounts">
            <span class="um-title-text">
                <i class="fa-solid fa-address-card um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Cari</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.cari-entries.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.cari-entries.*')])>Cari hareketleri</a>
            <a href="{{ route('admin.persons.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.persons.*') || ($onParty && $partyType === \App\Enums\PartyType::Person)])>Kişiler</a>
            <a href="{{ route('admin.companies.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.companies.*') || ($onParty && $partyType === \App\Enums\PartyType::Company)])>Şirketler</a>
        </div>
    </div>

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.cash-categories.*', 'admin.banks.*')]) id="um-sec-definitions">
        <button type="button" class="um-title" data-um-toggle="um-sec-definitions">
            <span class="um-title-text">
                <i class="fa-solid fa-sliders um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Tanımlar</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
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

    <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.reports.*')]) id="um-sec-reports">
        <button type="button" class="um-title" data-um-toggle="um-sec-reports">
            <span class="um-title-text">
                <i class="fa-solid fa-chart-column um-title-icon" aria-hidden="true"></i>
                <span class="um-title-label">Raporlar</span>
            </span>
            <span class="um-caret" aria-hidden="true"></span>
        </button>
        <div class="um-items">
            <a href="{{ route('admin.reports.monthly-vouchers') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.monthly-vouchers*')])>Aylık koçanlar</a>
            <a href="{{ route('admin.reports.site-exits') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.site-exits*')])>Şantiye çıkış raporları</a>
            <a href="{{ route('admin.reports.dump-sites') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.dump-sites') || request()->routeIs('admin.reports.dump-sites.export')])>Döküm yeri raporları</a>
            <a href="{{ route('admin.reports.dump-site-payments') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.dump-site-payments*')])>Döküm yeri ödeme</a>
            <a href="{{ route('admin.reports.fuel-stations') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.fuel-stations') || request()->routeIs('admin.reports.fuel-stations.export')])>Yakıt yeri raporları</a>
            <a href="{{ route('admin.reports.fuel-stations-monthly') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.fuel-stations-monthly*')])>Yakıt yeri aylık</a>
            <a href="{{ route('admin.reports.ticket-control') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.ticket-control*')])>Hareket fişi kontrol</a>
            <a href="{{ route('admin.reports.vehicle-monthly') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-monthly*')])>Plaka aylık döküm</a>
            <a href="{{ route('admin.reports.vehicle-periodic') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-periodic*')])>Plaka dönemsel</a>
            <a href="{{ route('admin.reports.vehicle-site-monthly') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-site-monthly*')])>Plaka şantiye döküm</a>
            <a href="{{ route('admin.reports.vehicle-balance') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-balance*')])>Plaka bakiye</a>
            <a href="{{ route('admin.reports.owner-monthly') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.owner-monthly*')])>Plaka sahibi aylık döküm</a>
            <a href="{{ route('admin.reports.owner-periodic') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.owner-periodic*')])>Plaka sahibi dönemsel</a>
            <a href="{{ route('admin.reports.owner-balance') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.owner-balance*')])>Plaka sahibi bakiye</a>
            <a href="{{ route('admin.reports.vehicle-cost') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-cost', 'admin.reports.vehicle-cost.export')])>Araç maliyet</a>
            <a href="{{ route('admin.reports.vehicle-cost.owners') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-cost.owners*')])>Plaka sahibi özet</a>
            <a href="{{ route('admin.reports.company-balance') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.company-balance*')])>Firma bakiye</a>
            <a href="{{ route('admin.reports.company-ledger') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.company-ledger*')])>Firma cari</a>
            <a href="{{ route('admin.reports.site-cari') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.site-cari*')])>Şantiye cari</a>
            <a href="{{ route('admin.reports.site-expense') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.site-expense*')])>Şantiye gider</a>
            <a href="{{ route('admin.reports.cash-movements') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.cash-movements*')])>Kasa hareketleri</a>
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
