@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="vehicle-cost-report"
        heading="Araç maliyet"
        icon="fa-solid fa-chart-pie"
        description="Seçilen plaka sahibinin araçlarında dönemsel gelir, yakıt ve piyasa maliyetini görün. Durum = Gelir − (Yakıt + Piyasa)."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Araç maliyet' => null]"
    >
        @include('admin.reports.vehicle-cost.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.vehicle-cost.partials.table')
        @elseif ($ready)
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="5"
                        icon="fa-solid fa-chart-pie"
                        title="Plaka sahibi bulunamadı."
                        text="Seçilen plaka sahibi rapora dahil edilemedi."
                    />
                </tbody>
            </x-table.list>
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
