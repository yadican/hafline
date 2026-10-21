@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="vehicle-site-monthly-report"
        heading="Plaka şantiye döküm"
        icon="fa-solid fa-truck-ramp-box"
        description="Seçilen plaka ve şantiye için aylık taşıma ile yakıt hareketlerini borç / alacak dökümü olarak görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Plaka şantiye döküm' => null]"
    >
        @include('admin.reports.vehicle-site-monthly.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.vehicle-site-monthly.partials.table')
        @elseif ($ready)
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="7"
                        icon="fa-solid fa-truck-ramp-box"
                        title="Kayıt bulunamadı."
                        text="Seçilen plaka veya şantiye rapora dahil edilemedi."
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
                        text="Plakayı yazıp listeden seçin, şantiyeyi belirleyin; dönemi seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
