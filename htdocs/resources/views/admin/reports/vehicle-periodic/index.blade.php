@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="vehicle-periodic-report"
        heading="Plaka dönemsel"
        icon="fa-solid fa-chart-simple"
        description="Seçilen aracın dönemdeki sefer, tonaj ve yakıt litrelerini görün (cari değil)."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Plaka dönemsel' => null]"
    >
        @include('admin.reports.vehicle-periodic.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.vehicle-periodic.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="5"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Plakayı yazıp listeden seçin; dönemi belirleyip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
