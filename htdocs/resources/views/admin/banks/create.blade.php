@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Yeni banka"
        icon="fa-solid fa-plus"
        description="Çek, senet ve kredi kayıtlarında seçilecek yeni bir banka ekleyin."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Tanımlar' => null, 'Bankalar' => route('admin.banks.index'), 'Yeni' => null]"
        :back="route('admin.banks.index')"
    >
        @include('admin.banks.partials.create.form')
    </x-panel.page>
@endsection
