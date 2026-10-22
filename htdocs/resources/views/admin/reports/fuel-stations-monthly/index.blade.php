@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="fuel-station-monthly-report"
        heading="Yakıt yeri aylık"
        icon="fa-solid fa-gas-pump"
        description="Seçilen yakıt yerinde plaka × gün litre dökümünü görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Yakıt yeri aylık' => null]"
    >
        @include('admin.reports.fuel-stations-monthly.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.fuel-stations-monthly.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="4"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Yakıt yeri ve ayı seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
