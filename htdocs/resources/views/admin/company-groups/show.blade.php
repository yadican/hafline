@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Şirket grubu detayı"
        icon="fa-solid fa-sitemap"
        :description="$group->name"
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, 'Şirket grupları' => route('admin.company-groups.index'), 'Detay' => null]"
        :back="route('admin.company-groups.index')"
    >
        <x-slot:actions>
            @can('edit-company-group')
                <x-ui.button variant="edit" icon="fa-solid fa-pen" :href="route('admin.company-groups.edit', $group)">Düzenle</x-ui.button>
            @endcan
        </x-slot:actions>

        <div class="panel-form-grid">
            <x-form.group legend="Şirket grubu" icon="fa-solid fa-sitemap" description="Grup adı, durumu ve şirketleri">
                <x-form.field label="Ad">
                    <div>{{ $group->name }}</div>
                </x-form.field>
                <x-form.field label="Durum">
                    <div><x-ui.active-badge :active="$group->active" /></div>
                </x-form.field>
                <x-form.field label="Şirketler">
                    @if ($group->companies->isNotEmpty())
                        <span class="panel-chips">
                            @foreach ($group->companies->sortBy(fn ($company) => $company->party?->name) as $company)
                                <a class="panel-chip" href="{{ route('admin.parties.show', $company->party_id) }}">{{ $company->party?->name }}</a>
                            @endforeach
                        </span>
                    @else
                        <div class="panel-chips-empty">Bu grupta henüz şirket yok.</div>
                    @endif
                </x-form.field>
            </x-form.group>
        </div>
    </x-panel.page>
@endsection
