@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Cari hareketini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$kind->label().' kaydını güncelleyin. Tür değiştirilemez.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari hareketleri' => route('admin.cari-entries.index'), 'Düzenle' => null]"
        :back="route('admin.cari-entries.index')"
    >
        <form method="POST" action="{{ route('admin.cari-entries.update', ['kind' => $kind->value, 'entry' => $entry]) }}" x-data="{ kind: @js($form['kind']) }" data-cari-entry data-party-url="{{ route('admin.cari-entries.lookups.party', ['party' => '__ID__']) }}" data-plate-url="{{ route('admin.cari-entries.lookups.plate') }}">
            @csrf
            @method('PUT')
            <x-form.grid :model="$form">
                @include('admin.cari-entries.partials.form.fields', ['locked' => true])
                <x-form.actions label="Güncelle" :cancel="route('admin.cari-entries.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/cari-entries.js')
@endpush
