@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="site-expense-report"
        heading="Şantiye gider"
        icon="fa-solid fa-file-invoice-dollar"
        description="Seçilen şantiyenin gelir / gider hareketlerini dönem, devir ve yürüyen bakiye ile görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Şantiye gider' => null]"
    >
        @include('admin.reports.site-expense.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.site-expense.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="6"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Şantiye ve dönemi seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
