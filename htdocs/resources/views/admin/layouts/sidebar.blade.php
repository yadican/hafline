<div class="um-nav" id="um-nav">
    @can('view-dashboard')
        <div class="um-section" id="um-sec-home">
            <a href="{{ route('admin.dashboard') }}" @class(['um-title', 'um-active' => request()->routeIs('admin.dashboard')])>
                <span class="um-title-text">
                    <i class="fa-solid fa-gauge um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Gösterge paneli</span>
                </span>
            </a>
        </div>
    @endcan

    @canany(['view-haul', 'view-machine-work'])
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.hauls.*', 'admin.machine-works.*')]) id="um-sec-tickets">
            <button type="button" class="um-title" data-um-toggle="um-sec-tickets">
                <span class="um-title-text">
                    <i class="fa-solid fa-file-invoice um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Fiş işlemleri</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                @can('view-haul')
                    <a href="{{ route('admin.hauls.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.hauls.*')])>Taşıma fişleri</a>
                @endcan
                @can('view-machine-work')
                    <a href="{{ route('admin.machine-works.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.machine-works.*')])>Makine çalışma fişleri</a>
                @endcan
            </div>
        </div>
    @endcanany

    @can('view-shipment')
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.shipments.*')]) id="um-sec-transport">
            <button type="button" class="um-title" data-um-toggle="um-sec-transport">
                <span class="um-title-text">
                    <i class="fa-solid fa-truck-moving um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Nakliye</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                <a href="{{ route('admin.shipments.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.shipments.*')])>Seferler</a>
            </div>
        </div>
    @endcan

    @canany(['view-fuel-receipt', 'view-fuel-station'])
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.fuel-receipts.*', 'admin.fuel-stations.*')]) id="um-sec-fuel">
            <button type="button" class="um-title" data-um-toggle="um-sec-fuel">
                <span class="um-title-text">
                    <i class="fa-solid fa-gas-pump um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Yakıt</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                @can('view-fuel-receipt')
                    <a href="{{ route('admin.fuel-receipts.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.fuel-receipts.*')])>Yakıt fişleri</a>
                @endcan
                @can('view-fuel-station')
                    <a href="{{ route('admin.fuel-stations.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.fuel-stations.*')])>Yakıt kaynakları ve depo</a>
                @endcan
            </div>
        </div>
    @endcanany

    @php
        $partyType = request()->route('party') instanceof \App\Models\Party
            ? request()->route('party')->type
            : \App\Enums\PartyType::tryFrom((string) request('type'));
        $onParty = request()->routeIs('admin.parties.*');
    @endphp
    @canany(['view-party', 'view-cari-entry'])
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.parties.*', 'admin.persons.*', 'admin.companies.*', 'admin.cari-entries.*')]) id="um-sec-accounts">
            <button type="button" class="um-title" data-um-toggle="um-sec-accounts">
                <span class="um-title-text">
                    <i class="fa-solid fa-address-card um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Cari</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                @can('view-cari-entry')
                    <a href="{{ route('admin.cari-entries.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.cari-entries.*')])>Cari hareketleri</a>
                @endcan
                @can('view-party')
                    <a href="{{ route('admin.persons.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.persons.*') || ($onParty && $partyType === \App\Enums\PartyType::Person)])>Kişiler</a>
                    <a href="{{ route('admin.companies.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.companies.*') || ($onParty && $partyType === \App\Enums\PartyType::Company)])>Şirketler</a>
                @endcan
            </div>
        </div>
    @endcanany

    @canany(['view-cash-category', 'view-bank'])
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.cash-categories.*', 'admin.banks.*')]) id="um-sec-definitions">
            <button type="button" class="um-title" data-um-toggle="um-sec-definitions">
                <span class="um-title-text">
                    <i class="fa-solid fa-sliders um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Tanımlar</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                @can('view-cash-category')
                    <a href="{{ route('admin.cash-categories.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.cash-categories.*')])>Kasa kategorileri</a>
                @endcan
                @can('view-bank')
                    <a href="{{ route('admin.banks.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.banks.*')])>Kasa / Banka / POS</a>
                @endcan
            </div>
        </div>
    @endcanany

    @canany(['view-vehicle', 'view-vehicle-class'])
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.vehicles.*', 'admin.vehicle-classes.*')]) id="um-sec-fleet">
            <button type="button" class="um-title" data-um-toggle="um-sec-fleet">
                <span class="um-title-text">
                    <i class="fa-solid fa-truck um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Filo</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                @can('view-vehicle')
                    <a href="{{ route('admin.vehicles.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.vehicles.*')])>Araçlar</a>
                @endcan
                @can('view-vehicle-class')
                    <a href="{{ route('admin.vehicle-classes.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.vehicle-classes.*')])>Araç sınıfları</a>
                @endcan
            </div>
        </div>
    @endcanany

    @canany(['view-site', 'view-dump-site', 'view-haul-route'])
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.sites.*', 'admin.dump-sites.*', 'admin.haul-routes.*')]) id="um-sec-field">
            <button type="button" class="um-title" data-um-toggle="um-sec-field">
                <span class="um-title-text">
                    <i class="fa-solid fa-map-location-dot um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Saha</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                @can('view-site')
                    <a href="{{ route('admin.sites.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.sites.*')])>Şantiyeler</a>
                @endcan
                @can('view-dump-site')
                    <a href="{{ route('admin.dump-sites.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.dump-sites.*')])>Döküm yerleri</a>
                @endcan
                @can('view-haul-route')
                    <a href="{{ route('admin.haul-routes.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.haul-routes.*')])>Güzergahlar ve fiyatlar</a>
                @endcan
            </div>
        </div>
    @endcanany

    @canany(['view-employee', 'view-employee-salary', 'view-employee-payment', 'view-employee-bonus', 'view-report-employee-monthly', 'view-report-employee-annual'])
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.employees.*', 'admin.employee-salaries.*', 'admin.employee-payments.*', 'admin.employee-bonuses.*', 'admin.reports.employee-monthly*', 'admin.reports.employee-annual*')]) id="um-sec-personnel">
            <button type="button" class="um-title" data-um-toggle="um-sec-personnel">
                <span class="um-title-text">
                    <i class="fa-solid fa-id-badge um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Personel</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                @can('view-employee')
                    <a href="{{ route('admin.employees.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.employees.*')])>Personel kartları</a>
                @endcan
                @can('view-employee-salary')
                    <a href="{{ route('admin.employee-salaries.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.employee-salaries.*')])>Maaş kartları</a>
                @endcan
                @can('view-employee-payment')
                    <a href="{{ route('admin.employee-payments.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.employee-payments.*')])>Ödeme girişi</a>
                @endcan
                @can('view-employee-bonus')
                    <a href="{{ route('admin.employee-bonuses.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.employee-bonuses.*')])>Prim girişi</a>
                @endcan
                @can('view-report-employee-monthly')
                    <a href="{{ route('admin.reports.employee-monthly') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.employee-monthly*')])>Aylık döküm</a>
                @endcan
                @can('view-report-employee-annual')
                    <a href="{{ route('admin.reports.employee-annual') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.employee-annual*')])>Yıllık ödeme</a>
                @endcan
            </div>
        </div>
    @endcanany

    @canany([
        'view-report-monthly-vouchers',
        'view-report-site-exits',
        'view-report-dump-sites',
        'view-report-dump-site-payments',
        'view-report-fuel-stations',
        'view-report-fuel-stations-monthly',
        'view-report-ticket-control',
        'view-report-vehicle-monthly',
        'view-report-vehicle-periodic',
        'view-report-vehicle-site-monthly',
        'view-report-vehicle-balance',
        'view-report-owner-monthly',
        'view-report-owner-periodic',
        'view-report-owner-balance',
        'view-report-vehicle-cost',
        'view-report-vehicle-cost-owners',
        'view-report-company-balance',
        'view-report-company-ledger',
        'view-report-site-cari',
        'view-report-site-expense',
        'view-report-cash-movements',
    ])
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.reports.*') || request()->routeIs('admin.reports.employee-monthly*', 'admin.reports.employee-annual*')]) id="um-sec-reports">
            <button type="button" class="um-title" data-um-toggle="um-sec-reports">
                <span class="um-title-text">
                    <i class="fa-solid fa-chart-column um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Raporlar</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                @can('view-report-monthly-vouchers')
                    <a href="{{ route('admin.reports.monthly-vouchers') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.monthly-vouchers*')])>Aylık koçanlar</a>
                @endcan
                @can('view-report-site-exits')
                    <a href="{{ route('admin.reports.site-exits') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.site-exits*')])>Şantiye çıkış raporları</a>
                @endcan
                @can('view-report-dump-sites')
                    <a href="{{ route('admin.reports.dump-sites') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.dump-sites') || request()->routeIs('admin.reports.dump-sites.export')])>Döküm yeri raporları</a>
                @endcan
                @can('view-report-dump-site-payments')
                    <a href="{{ route('admin.reports.dump-site-payments') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.dump-site-payments*')])>Döküm yeri ödeme</a>
                @endcan
                @can('view-report-fuel-stations')
                    <a href="{{ route('admin.reports.fuel-stations') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.fuel-stations') || request()->routeIs('admin.reports.fuel-stations.export')])>Yakıt yeri raporları</a>
                @endcan
                @can('view-report-fuel-stations-monthly')
                    <a href="{{ route('admin.reports.fuel-stations-monthly') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.fuel-stations-monthly*')])>Yakıt yeri aylık</a>
                @endcan
                @can('view-report-ticket-control')
                    <a href="{{ route('admin.reports.ticket-control') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.ticket-control*')])>Hareket fişi kontrol</a>
                @endcan
                @can('view-report-vehicle-monthly')
                    <a href="{{ route('admin.reports.vehicle-monthly') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-monthly*')])>Plaka aylık döküm</a>
                @endcan
                @can('view-report-vehicle-periodic')
                    <a href="{{ route('admin.reports.vehicle-periodic') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-periodic*')])>Plaka dönemsel</a>
                @endcan
                @can('view-report-vehicle-site-monthly')
                    <a href="{{ route('admin.reports.vehicle-site-monthly') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-site-monthly*')])>Plaka şantiye döküm</a>
                @endcan
                @can('view-report-vehicle-balance')
                    <a href="{{ route('admin.reports.vehicle-balance') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-balance*')])>Plaka bakiye</a>
                @endcan
                @can('view-report-owner-monthly')
                    <a href="{{ route('admin.reports.owner-monthly') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.owner-monthly*')])>Plaka sahibi aylık döküm</a>
                @endcan
                @can('view-report-owner-periodic')
                    <a href="{{ route('admin.reports.owner-periodic') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.owner-periodic*')])>Plaka sahibi dönemsel</a>
                @endcan
                @can('view-report-owner-balance')
                    <a href="{{ route('admin.reports.owner-balance') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.owner-balance*')])>Plaka sahibi bakiye</a>
                @endcan
                @can('view-report-vehicle-cost')
                    <a href="{{ route('admin.reports.vehicle-cost') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-cost', 'admin.reports.vehicle-cost.export')])>Araç maliyet</a>
                @endcan
                @can('view-report-vehicle-cost-owners')
                    <a href="{{ route('admin.reports.vehicle-cost.owners') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.vehicle-cost.owners*')])>Plaka sahibi özet</a>
                @endcan
                @can('view-report-company-balance')
                    <a href="{{ route('admin.reports.company-balance') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.company-balance*')])>Firma bakiye</a>
                @endcan
                @can('view-report-company-ledger')
                    <a href="{{ route('admin.reports.company-ledger') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.company-ledger*')])>Firma cari</a>
                @endcan
                @can('view-report-site-cari')
                    <a href="{{ route('admin.reports.site-cari') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.site-cari*')])>Şantiye cari</a>
                @endcan
                @can('view-report-site-expense')
                    <a href="{{ route('admin.reports.site-expense') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.site-expense*')])>Şantiye gider</a>
                @endcan
                @can('view-report-cash-movements')
                    <a href="{{ route('admin.reports.cash-movements') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.reports.cash-movements*')])>Kasa hareketleri</a>
                @endcan
            </div>
        </div>
    @endcanany

    @canany(['view-web-audit-log', 'view-api-audit-log'])
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.web-audit-logs.*', 'admin.api-audit-logs.*')]) id="um-sec-audit-logs">
            <button type="button" class="um-title" data-um-toggle="um-sec-audit-logs">
                <span class="um-title-text">
                    <i class="fa-solid fa-clipboard-list um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">İşlem logları</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                @can('view-web-audit-log')
                    <a href="{{ route('admin.web-audit-logs.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.web-audit-logs.*')])>Web logları</a>
                @endcan
                @can('view-api-audit-log')
                    <a href="{{ route('admin.api-audit-logs.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.api-audit-logs.*')])>API logları</a>
                @endcan
            </div>
        </div>
    @endcanany

    @canany(['view-users', 'view-role', 'view-permission'])
        <div @class(['um-section', 'um-collapsed' => ! request()->routeIs('admin.users.*', 'admin.roles.*', 'admin.permissions.*')]) id="um-sec-authority">
            <button type="button" class="um-title" data-um-toggle="um-sec-authority">
                <span class="um-title-text">
                    <i class="fa-solid fa-shield-halved um-title-icon" aria-hidden="true"></i>
                    <span class="um-title-label">Yetki yönetimi</span>
                </span>
                <span class="um-caret" aria-hidden="true"></span>
            </button>
            <div class="um-items">
                @can('view-users')
                    <a href="{{ route('admin.users.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.users.*')])>Kullanıcılar</a>
                @endcan
                @can('view-role')
                    <a href="{{ route('admin.roles.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.roles.*')])>Roller</a>
                @endcan
                @can('view-permission')
                    <a href="{{ route('admin.permissions.index') }}" @class(['um-link', 'um-active' => request()->routeIs('admin.permissions.*')])>İzinler</a>
                @endcan
            </div>
        </div>
    @endcanany

    @can('view-example')
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
    @endcan
</div>
