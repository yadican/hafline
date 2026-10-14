@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Döküm yerini düzenle"
        icon="fa-solid fa-pen-to-square"
        :description="$dumpSite->name.' bilgilerini güncelleyin.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Saha' => null, 'Döküm yerleri' => route('admin.dump-sites.index'), 'Düzenle' => null]"
        :back="route('admin.dump-sites.index')"
    >
        @include('admin.dump-sites.partials.edit.form')
    </x-panel.page>
@endsection
