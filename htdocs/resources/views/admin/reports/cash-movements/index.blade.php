@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="cash-movements-report"
        heading="Kasa hareketleri"
        icon="fa-solid fa-cash-register"
        description="Şirket kasasına giren ve çıkan nakit hareketlerini devir ve yürüyen bakiye ile görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Kasa hareketleri' => null]"
    >
        @include('admin.reports.cash-movements.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.cash-movements.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="7"
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
