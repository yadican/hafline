@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="dump-site-payment-report"
        heading="Döküm yeri ödeme"
        icon="fa-solid fa-truck-ramp-box"
        description="Seçilen döküm yerinin dönem dökümlerini, ödemelerini, devir ve bakiyesini görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Döküm yeri ödeme' => null]"
    >
        @include('admin.reports.dump-site-payments.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.dump-site-payments.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="4"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Döküm yeri ve dönemi seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
