@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni etiket"
        icon="fa-solid fa-plus"
        description="Cari kartlarda kullanılacak yeni bir etiket ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, 'Etiketler' => route('admin.tags.index'), 'Yeni' => null]"
        :back="route('admin.tags.index')"
    >
        <form method="POST" action="{{ route('admin.tags.store') }}">
            @csrf
            <x-form.grid :model="['tone' => \App\Enums\TagTone::Neutral->value]">
                @include('admin.tags.partials.form.fields')
                <x-form.actions label="Kaydet" :cancel="route('admin.tags.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
