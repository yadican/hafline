@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="fuel-station-report"
        heading="Yakıt yeri raporları"
        icon="fa-solid fa-gas-pump"
        description="Seçilen yakıt yerinde alınan yakıtı plaka bazında litre, birim fiyat ve tutar olarak görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Yakıt yeri' => null]"
    >
        @include('admin.reports.fuel-stations.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.fuel-stations.partials.table')
        @elseif ($ready)
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="5"
                        icon="fa-solid fa-gas-pump"
                        title="Bu dönemde yakıt fişi yok."
                        text="Seçilen yakıt yeri, ay ve araç tipi için yakıt fişi bulunamadı."
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
                        text="Yakıt yeri, yıl ve ayı seçip Raporu Göster’e basın. Araç tipi isteğe bağlıdır."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
