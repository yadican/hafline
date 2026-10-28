@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Şirket grupları"
        icon="fa-solid fa-sitemap"
        description="Bir işveren ile şantiye şirketleri gibi birlikte izlenen şirket cari kartlarını tek ad altında toplayın."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, 'Şirket grupları' => null]"
    >
        @include('admin.company-groups.partials.index.toolbar')
        <x-table.list>
            <thead>
                <tr>
                    <x-table.th sort="name">Grup</x-table.th>
                    <x-table.th sort="companies" class="is-center">Şirket</x-table.th>
                    <th class="is-status">Durum</th>
                    <th class="is-center is-actions">İşlem</th>
                </tr>
            </thead>
            @include('admin.company-groups.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$groups" />
    </x-panel.page>
@endsection
