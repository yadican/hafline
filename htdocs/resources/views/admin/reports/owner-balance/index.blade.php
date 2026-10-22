@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="owner-balance-report"
        heading="Plaka sahibi bakiye"
        icon="fa-solid fa-users"
        description="Plaka sahibi bazında alacak, elden, borç ve bakiyeyi dönem, şantiye veya yakıt yeri filtresiyle görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Plaka sahibi bakiye' => null]"
    >
        @include('admin.reports.owner-balance.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.owner-balance.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="5"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Şantiye, yakıt yeri ve dönemi seçip Raporu Göster’e basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
