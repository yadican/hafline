@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="vehicle-monthly-report"
        heading="Plaka aylık döküm"
        icon="fa-solid fa-truck"
        description="Seçilen aracın aylık taşıma, yakıt, ödeme ve alacak hareketlerini borç / alacak dökümü olarak görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Plaka aylık döküm' => null]"
    >
        @include('admin.reports.vehicle-monthly.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.vehicle-monthly.partials.table')
        @elseif ($ready)
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="7"
                        icon="fa-solid fa-truck"
                        title="Araç bulunamadı."
                        text="Seçilen araç rapora dahil edilemedi."
                    />
                </tbody>
            </x-table.list>
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="7"
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
