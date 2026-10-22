<form method="POST" action="{{ route('admin.roles.update', $role) }}">
    @csrf
    @method('PUT')
    <x-form.grid :model="$role">
        <x-form.group legend="Rol" icon="fa-solid fa-user-shield" description="Rol adı (guard değiştirilemez)">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
            <x-form.field label="Guard">
                <span class="panel-badge">{{ $role->guard_name }}</span>
            </x-form.field>
        </x-form.group>

        <x-form.group legend="İzinler" icon="fa-solid fa-key" description="Bu role verilecek yetkiler" class="is-wide">
            @include('admin.roles.partials.permission-groups', [
                'permissionGroups' => $permissionGroups,
                'selectedPermissions' => $selectedPermissions,
            ])
        </x-form.group>

        <x-form.actions label="Güncelle" :cancel="route('admin.roles.index')" />
    </x-form.grid>
</form>
