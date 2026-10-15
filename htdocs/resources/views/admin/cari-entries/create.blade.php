@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni cari hareketi"
        icon="fa-solid fa-plus"
        description="Borç: cari bize borçlu ya da biz ödedik. Alacak: biz cariye borçluyuz. Kasa girişinde borç, kasaya giren paradır."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari hareketleri' => route('admin.cari-entries.index'), 'Yeni' => null]"
        :back="route('admin.cari-entries.index')"
    >
        <form method="POST" action="{{ route('admin.cari-entries.store') }}" x-data="{ kind: @js(old('kind', $form['kind'])) }">
            @csrf
            <x-form.grid :model="$form">
                @include('admin.cari-entries.partials.form.fields')
                <x-form.actions label="Kaydet" :cancel="route('admin.cari-entries.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
