@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Makine çalışma fişini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$work->ticket_no.' numaralı fişi güncelleyin. Saat ücreti siz değiştirmedikçe kayıtlı değerinde kalır.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Makine çalışma fişleri' => route('admin.machine-works.index'), 'Düzenle' => null]"
        :back="route('admin.machine-works.index')"
    >
        <form method="POST" action="{{ route('admin.machine-works.update', $work) }}" data-machine-entry data-rate-url="{{ route('admin.machine-works.lookups.rate') }}" data-operator-url="{{ route('admin.machine-works.lookups.operator') }}" data-keep-rate>
            @csrf
            @method('PUT')
            <x-form.grid :model="$work">
                @include('admin.machine-works.partials.form.fields')
                <x-form.actions label="Güncelle" :cancel="route('admin.machine-works.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/machine-works.js')
@endpush
