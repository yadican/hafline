@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="owner-periodic-report"
        heading="Plaka sahibi dönemsel"
        icon="fa-solid fa-chart-simple"
        description="Seçilen plaka sahibinin dönemdeki sefer, tonaj ve yakıt litrelerini araç bazında görün (cari değil)."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Plaka sahibi dönemsel' => null]"
    >
        @include('admin.reports.owner-periodic.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.owner-periodic.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="5"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Plaka sahibini ve dönemi seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
