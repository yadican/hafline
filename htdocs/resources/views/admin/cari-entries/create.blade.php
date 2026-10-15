@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni cari hareketi"
        icon="fa-solid fa-plus"
        description="Borç: cari bize borçlu ya da biz ödedik. Alacak: biz cariye borçluyuz. Kasa girişinde borç, kasaya giren paradır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari hareketleri' => route('admin.cari-entries.index'), 'Yeni' => null]"
        :back="route('admin.cari-entries.index')"
    >
        <form method="POST" action="{{ route('admin.cari-entries.store') }}" x-data="{ kind: @js(old('kind', $form['kind'])) }" data-cari-entry data-party-url="{{ route('admin.cari-entries.lookups.party', ['party' => '__ID__']) }}" data-plate-url="{{ route('admin.cari-entries.lookups.plate') }}">
            @csrf
            <x-form.grid :model="$form">
                @include('admin.cari-entries.partials.form.fields')
                <x-form.actions label="Kaydet" :cancel="route('admin.cari-entries.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection

@push('scripts')
    @vite('resources/js/cari-entries.js')
@endpush
