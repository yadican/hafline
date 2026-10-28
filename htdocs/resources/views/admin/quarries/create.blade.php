@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni ocak / yükleme yeri"
        icon="fa-solid fa-plus"
        description="Kaydettikten sonra düzenleme ekranından alış ve nakliyeci fiyatlarını girin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Nakliye' => null, 'Ocaklar ve yükleme yerleri' => route('admin.quarries.index'), 'Yeni' => null]"
        :back="route('admin.quarries.index')"
    >
        <form method="POST" action="{{ route('admin.quarries.store') }}">
            @csrf
            <x-form.grid>
                @include('admin.quarries.partials.form')
                <x-form.actions label="Kaydet" :cancel="route('admin.quarries.index')" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
