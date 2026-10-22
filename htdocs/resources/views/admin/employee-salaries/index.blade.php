@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="employee-salaries-report"
        heading="Personel maaş kartları"
        icon="fa-solid fa-file-invoice-dollar"
        description="Dönem bazında gün sayısı girerek hakedilen maaşı hesaplayın ve kaydedin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Personel' => null, 'Maaş kartları' => null]"
    >
        @include('admin.employee-salaries.partials.filters')

        @if ($ready && $grid !== null)
            @include('admin.employee-salaries.partials.table')
        @else
            <x-table.list class="report-table">
                <tbody>
                    <x-table.empty
                        colspan="10"
                        icon="fa-solid fa-filter"
                        title="Maaş kartını görmek için dönem seçin."
                        text="Ay ve yılı seçip Bul’a basın."
                    />
                </tbody>
            </x-table.list>
        @endif
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/reports.js')
@endpush
