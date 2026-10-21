@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="dump-site-report"
        heading="Döküm yeri raporları"
        icon="fa-solid fa-chart-column"
        description="Seçilen döküm yerine giden seferleri plaka ve güzergah matrisinde görün."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Raporlar' => null, 'Döküm yeri' => null]"
    >
        @include('admin.reports.dump-sites.partials.filters')

        @if ($ready && $matrix !== null)
            @include('admin.reports.dump-sites.partials.matrix')
        @elseif ($ready)
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="2"
                        icon="fa-solid fa-chart-column"
                        title="Bu dönemde sefer yok."
                        text="Seçilen döküm yeri, dönem ve plaka tipi için taşıma fişi bulunamadı."
                    />
                </tbody>
            </x-table.list>
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="2"
                        icon="fa-solid fa-filter"
                        title="Raporu görmek için seçim yapın."
                        text="Döküm yeri ve dönemi seçip Raporu Göster’e basın. Plaka tipi isteğe bağlıdır."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
