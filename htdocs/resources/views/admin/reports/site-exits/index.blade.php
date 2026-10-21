@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="site-exit-report"
        heading="Şantiye çıkış raporları"
        icon="fa-solid fa-chart-column"
        description="Seçilen şantiyeden çıkan seferleri plaka ve güzergah matrisinde görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Şantiye çıkış' => null]"
    >
        @include('admin.reports.site-exits.partials.filters')

        @if ($ready && $matrix !== null)
            @include('admin.reports.site-exits.partials.matrix')
        @elseif ($ready)
            <x-table.list class="site-exit-table">
                <tbody>
                    <x-table.empty
                        colspan="2"
                        icon="fa-solid fa-chart-column"
                        title="Bu dönemde sefer yok."
                        text="Seçilen şantiye, dönem ve plaka tipi için taşıma fişi bulunamadı."
                    />
                </tbody>
            </x-table.list>
        @else
            <x-table.list class="site-exit-table">
                <tbody>
                    <x-table.empty
                        colspan="2"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Şantiye ve dönemi seçip Raporu Göster’e basın. Plaka tipi isteğe bağlıdır."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
