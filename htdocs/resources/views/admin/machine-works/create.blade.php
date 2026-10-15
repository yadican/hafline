@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni makine çalışma fişi"
        icon="fa-solid fa-plus"
        description="Tutar, çalışılan saat × saat ücreti olarak makine sahibinin carisine alacak yazılır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Makine çalışma fişleri' => route('admin.machine-works.index'), 'Yeni fiş' => null]"
        :back="route('admin.machine-works.index')"
    >
        <form method="POST" action="{{ route('admin.machine-works.store') }}" data-machine-entry data-rate-url="{{ route('admin.machine-works.lookups.rate') }}" data-operator-url="{{ route('admin.machine-works.lookups.operator') }}">
            @csrf
            <x-form.grid>
                @include('admin.machine-works.partials.form.fields', ['work' => null])
                <x-form.actions label="Kaydet" :cancel="route('admin.machine-works.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/machine-works.js')
@endpush
