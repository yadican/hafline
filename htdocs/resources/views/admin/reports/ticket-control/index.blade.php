@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="ticket-control-report"
        heading="Hareket fişi kontrol"
        icon="fa-solid fa-clipboard-check"
        description="Fiş teslim, hareket ve yakıt fiş adetlerini karşılaştırın."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Hareket fişi kontrol' => null]"
    >
        @include('admin.reports.ticket-control.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.ticket-control.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="2"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Dönemi seçip Raporu Göster’e basın; filtreler isteğe bağlıdır."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
