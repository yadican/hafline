@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="company-balance-report"
        heading="Firma bakiye"
        icon="fa-solid fa-scale-balanced"
        description="Satıcı, alıcı, petrol veya piyasa carilerinin dönem alacak / borç ve kapanış bakiyesini görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Firma bakiye' => null]"
    >
        @include('admin.reports.company-balance.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.company-balance.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="4"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Cari türü ve dönemi seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
