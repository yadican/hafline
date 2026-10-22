<form method="POST" action="{{ route('admin.roles.store') }}">
    @csrf
    <x-form.grid>
        <x-form.group legend="Rol" icon="fa-solid fa-user-shield" description="Rol adı">
            <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
        </x-form.group>

        <x-form.group legend="İzinler" icon="fa-solid fa-key" description="Bu role verilecek yetkiler" class="is-wide">
            @include('admin.roles.partials.permission-groups', [
                'permissionGroups' => $permissionGroups,
                'selectedPermissions' => $selectedPermissions,
            ])
        </x-form.group>

        <x-form.actions label="Kaydet" :cancel="route('admin.roles.index')" />
    </x-form.grid>
</form>
