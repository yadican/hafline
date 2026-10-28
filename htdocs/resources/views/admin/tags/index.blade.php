@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="Etiketler"
        icon="fa-solid fa-tag"
        description="Cari kartlara verilen serbest etiketler (Büyük müşteri, Takipte, Vadeli çalışır gibi). Listeleme ve filtre içindir; hiçbir hesaplamayı etkilemez."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Cari' => null, 'Etiketler' => null]"
    >
        @include('admin.tags.partials.index.toolbar')
        <x-table.list>
            <thead>
                <tr>
                    <x-table.th sort="name">Etiket</x-table.th>
                    <x-table.th sort="parties" class="is-center">Cari</x-table.th>
                    <th class="is-status">Durum</th>
                    <th class="is-center is-actions">İşlem</th>
                </tr>
            </thead>
            @include('admin.tags.partials.index.table-body')
        </x-table.list>
        <x-table.pager :items="$tags" />
    </x-panel.page>
@endsection
