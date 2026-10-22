@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="monthly-voucher-report"
        heading="Aylık koçanlar"
        icon="fa-solid fa-calendar-days"
        description="Seçilen güzergahta plaka × gün taşıma sayısını görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Aylık koçanlar' => null]"
    >
        @include('admin.reports.monthly-vouchers.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.monthly-vouchers.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="4"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Güzergah, ay ve yönü seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
