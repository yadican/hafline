@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="owner-monthly-report"
        heading="Plaka sahibi aylık döküm"
        icon="fa-solid fa-user-tie"
        description="Seçilen plaka sahibinin araçlarına ait aylık taşıma, yakıt, ödeme ve alacak hareketlerini borç / alacak dökümü olarak görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Plaka sahibi aylık döküm' => null]"
    >
        @include('admin.reports.owner-monthly.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.owner-monthly.partials.table')
        @elseif ($ready)
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="7"
                        icon="fa-solid fa-user-tie"
                        title="Plaka sahibi bulunamadı."
                        text="Seçilen plaka sahibi rapora dahil edilemedi."
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
                        text="Plaka sahibini seçin; dönemi belirleyip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
