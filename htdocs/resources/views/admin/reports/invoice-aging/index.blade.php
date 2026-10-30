@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="invoice-aging-report"
        heading="Fatura yaşlandırma"
        icon="fa-solid fa-hourglass-half"
        description="Açık fatura kalanlarını cari bazında vade dilimlerine ayırın (Alacaklar / Borçlar)."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Fatura yaşlandırma' => null]"
    >
        @include('admin.reports.invoice-aging.partials.filters')

        @if ($ready && $report !== null)
            @include('admin.reports.invoice-aging.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="4"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Alacaklar veya Borçlar seçip Rapor’a basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection
