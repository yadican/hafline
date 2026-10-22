@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="employee-annual-report"
        heading="Personel yıllık ödeme"
        icon="fa-solid fa-calendar-days"
        description="Seçilen personelin yıllık maaş, prim ve ödeme hareketlerini yürüyen devir ile görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Yıllık ödeme' => null]"
    >
        @include('admin.reports.employee-annual.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.employee-annual.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="6"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Personel ve yılı seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
