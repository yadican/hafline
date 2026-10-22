@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="site-cari-report"
        heading="Şantiye cari"
        icon="fa-solid fa-helmet-safety"
        description="Şantiye bazında döküm, nakliye, yakıt, diğer gider ve hakediş toplamlarını dönem seçerek görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Şantiye cari' => null]"
    >
        @include('admin.reports.site-cari.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.site-cari.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="9"
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
