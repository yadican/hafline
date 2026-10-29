@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        :heading="$title"
        :icon="$type->icon()"
        :description="$type === \App\Enums\PartyType::Person ? 'Araç sahibi, şoför veya personel gibi bir kişi için cari kart açın.' : 'Firma, döküm işletmecisi veya akaryakıtçı gibi bir şirket için cari kart açın.'"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari kartlar' => null, $type->pluralLabel() => route($type->indexRoute()), 'Yeni' => null]"
        :back="route($type->indexRoute())"
    >
        <form method="POST" action="{{ route('admin.parties.store') }}">
            @csrf
            <input type="hidden" name="type" value="{{ $type->value }}">
            <x-form.grid>
                @include('admin.parties.partials.form.fields', ['lockedRoles' => []])
                <x-form.actions label="Kaydet" :cancel="route($type->indexRoute())" />
            </x-form.grid>
        </form>
    </x-panel.page>
@endsection
