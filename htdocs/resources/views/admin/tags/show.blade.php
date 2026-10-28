@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Etiket detayı"
        icon="fa-solid fa-tag"
        :description="$tag->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, 'Etiketler' => route('admin.tags.index'), 'Detay' => null]"
        :back="route('admin.tags.index')"
    >
        <x-slot:actions>
            @can('edit-tag')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.tags.edit', $tag)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        <div class="panel-form-grid">
            <x-form.group legend="Etiket" icon="fa-solid fa-tag" description="Etiket, durumu ve bağlı cari kartlar">
                <x-form.field label="Etiket">
                    <div><x-ui.tag :tag="$tag" /></div>
                </x-form.field>
                <x-form.field label="Durum">
                    <div><x-ui.active-badge :active="$tag->active" /></div>
                </x-form.field>
                <x-form.field label="Cari kartlar">
                    @if ($tag->parties->isNotEmpty())
                        <span class="panel-chips">
                            @foreach ($tag->parties as $party)
                                <a class="panel-chip" href="{{ route('admin.parties.show', $party) }}">{{ $party->name }}</a>
                            @endforeach
                        </span>
                    @else
                        <div class="panel-chips-empty">Bu etiket henüz bir cari karta verilmedi.</div>
                    @endif
                </x-form.field>
            </x-form.group>
        </div>
    </x-panel.page>
@endsection
