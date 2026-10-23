@php
    use App\Support\PermissionGroups;
@endphp

<div class="panel-form-grid">
    <x-form.group legend="Rol" icon="fa-solid fa-user-shield" description="Rol adı ve guard">
        <x-form.field label="Ad">
            <div>{{ $role->name }}</div>
        </x-form.field>
        <x-form.field label="Guard">
            <div><span class="panel-badge">{{ $role->guard_name }}</span></div>
        </x-form.field>
        <x-form.field label="İzin sayısı">
            <div>{{ $permissionGroups->flatten()->count() ?: '—' }}</div>
        </x-form.field>
    </x-form.group>

    <x-form.group legend="İzinler" icon="fa-solid fa-key" description="Bu role verilmiş yetkiler" class="is-wide">
        @forelse ($permissionGroups as $groupKey => $permissions)
            <x-form.field :label="PermissionGroups::labelFor($groupKey)">
                <div class="panel-chips">
                    @foreach ($permissions as $permission)
                        <span class="panel-chip">{{ $permission->name }}</span>
                    @endforeach
                </div>
            </x-form.field>
        @empty
            <p class="panel-hint">Bu role henüz izin verilmemiş.</p>
        @endforelse
    </x-form.group>
</div>
