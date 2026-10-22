@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="employee-monthly-report"
        heading="Personel aylık döküm"
        icon="fa-solid fa-chart-column"
        description="Dönem maaşı, prim, önceki devir ve kalan bakiyeyi personel bazında görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Aylık döküm' => null]"
    >
        @include('admin.reports.employee-monthly.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.employee-monthly.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="6"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Ay ve yılı seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
