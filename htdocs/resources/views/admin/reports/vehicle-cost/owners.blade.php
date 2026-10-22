@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="vehicle-cost-owner-summary"
        heading="Plaka sahibi özet"
        icon="fa-solid fa-users"
        description="Dönemde hareketi olan plaka sahiplerinin gelir, yakıt ve piyasa toplamlarını görün. Durum = Gelir − (Yakıt + Piyasa)."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Plaka sahibi özet' => null]"
    >
        @include('admin.reports.vehicle-cost.partials.owner-filters')

        @if ($ready && $report !== null)
            @include('admin.reports.vehicle-cost.partials.owner-table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="5"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Dönemi seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
