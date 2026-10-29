@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        :heading="$type->pluralLabel()"
        :icon="$type->icon()"
        :description="$type === \App\Enums\PartyType::Person ? 'Araç sahibi, şoför ve personel gibi kişilerin cari kartları.' : 'Firma, döküm işletmecisi ve akaryakıtçı gibi şirketlerin cari kartları.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari kartlar' => null, $type->pluralLabel() => null]"
    >
        @include('admin.parties.partials.index.toolbar')
        <x-table.list>
            @include('admin.parties.partials.index.table-head')
            @include('admin.parties.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$parties" />
    </x-panel.page>
@endsection
