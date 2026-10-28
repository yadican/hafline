@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Etiketi düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$tag->name.' etiketinin bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, 'Etiketler' => route('admin.tags.index'), 'Düzenle' => null]"
        :back="route('admin.tags.index')"
    >
        <form method="POST" action="{{ route('admin.tags.update', $tag) }}">
            @csrf
            @method('PUT')
            <x-form.grid :model="$tag">
                @include('admin.tags.partials.form.fields')
                <x-form.actions label="Güncelle" :cancel="route('admin.tags.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
