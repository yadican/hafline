@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="company-ledger-report"
        heading="Firma cari"
        icon="fa-solid fa-book"
        description="Satıcı, alıcı, petrol veya piyasa cari hareketlerini dönem ve isteğe bağlı firma seçerek dökün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Firma cari' => null]"
    >
        @include('admin.reports.company-ledger.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.company-ledger.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="6"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Cari türü ve dönemi seçip Raporu Göster’e basın. Firma isteğe bağlıdır."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
