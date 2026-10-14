@php
    $sortQuery = request()->except(['page', 'sort', 'dir']);
    $sortActive = request('sort') === $field ? request('dir', 'desc') : null;
@endphp
<span class="panel-sort">
    <a
        href="{{ route('admin.hauls.index', [...$sortQuery, 'sort' => $field, 'dir' => 'asc']) }}"
        @class(['is-active' => $sortActive === 'asc'])
        title="Artan sırala"
    >&#9650;</a>
    <a
        href="{{ route('admin.hauls.index', [...$sortQuery, 'sort' => $field, 'dir' => 'desc']) }}"
        @class(['is-active' => $sortActive === 'desc'])
        title="Azalan sırala"
    >&#9660;</a>
</span>
