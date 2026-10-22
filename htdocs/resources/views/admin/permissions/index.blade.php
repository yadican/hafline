@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        heading="İzinler"
        icon="fa-solid fa-key"
        description="Sistemde tanımlı izinler (salt okunur). Yeni izinler seeder ile eklenir."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Yetki yönetimi' => null, 'İzinler' => null]"
    >
        <div class="permission-groups">
            @forelse ($permissionGroups as $groupKey => $permissions)
                <div class="permission-group">
                    <div class="permission-group-head">
                        <strong>{{ \App\Support\PermissionGroups::labelFor($groupKey) }}</strong>
                        <span class="panel-badge">{{ $permissions->count() }}</span>
                    </div>
                    <div class="permission-group-body">
                        @foreach ($permissions as $permission)
                            <span class="panel-badge" title="guard: {{ $permission->guard_name }}">
                                {{ $permission->name }}
                                <small class="text-muted">({{ $permission->guard_name }})</small>
                            </span>
                        @endforeach
                    </div>
                </div>
            @empty
                <x-table.empty colspan="1" icon="fa-solid fa-key" title="Henüz izin yok." text="RolePermissionSeeder çalıştırıldığında izinler oluşur." />
            @endforelse
        </div>
    </x-panel.page>
@endsection
