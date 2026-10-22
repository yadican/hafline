<form method="POST" action="{{ route('admin.roles.store') }}">
    @csrf
    <div x-data="{ guard: @js($selectedGuard) }">
        <x-form.grid>
            <x-form.group legend="Rol" icon="fa-solid fa-user-shield" description="Ad ve guard">
                <x-form.input name="name" label="Ad" maxlength="255" required autofocus />
                <x-form.select
                    hide-search
                    name="guard_name"
                    label="Guard"
                    :options="['web' => 'web (admin panel)', 'api' => 'api (mobil / JWT)']"
                    x-model="guard"
                    required
                />
            </x-form.group>

            <x-form.group legend="İzinler" icon="fa-solid fa-key" description="Seçilen guard’a ait izinler" class="is-wide">
                <template x-if="guard === 'web'">
                    <div>
                        @include('admin.roles.partials.permission-groups', [
                            'permissionGroups' => $webPermissionGroups,
                            'selectedPermissions' => $selectedGuard === 'web' ? $selectedPermissions : [],
                        ])
                    </div>
                </template>
                <template x-if="guard === 'api'">
                    <div>
                        @include('admin.roles.partials.permission-groups', [
                            'permissionGroups' => $apiPermissionGroups,
                            'selectedPermissions' => $selectedGuard === 'api' ? $selectedPermissions : [],
                        ])
                    </div>
                </template>
            </x-form.group>

            <x-form.actions label="Kaydet" :cancel="route('admin.roles.index')" />
        </x-form.grid>
    </div>
</form>
