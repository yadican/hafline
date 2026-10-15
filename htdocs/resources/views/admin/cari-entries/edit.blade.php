@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Cari hareketini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$kind->label().' kaydını güncelleyin. Tür değiştirilemez.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari hareketleri' => route('admin.cari-entries.index'), 'Düzenle' => null]"
        :back="route('admin.cari-entries.index')"
    >
        <form method="POST" action="{{ route('admin.cari-entries.update', ['kind' => $kind->value, 'entry' => $entry]) }}" x-data="{ kind: @js($form['kind']) }">
            @csrf
            @method('PUT')
            <x-form.grid :model="$form">
                @include('admin.cari-entries.partials.form.fields', ['locked' => true])
                <x-form.actions label="Güncelle" :cancel="route('admin.cari-entries.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
