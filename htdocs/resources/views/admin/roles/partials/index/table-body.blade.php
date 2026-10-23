<tbody>
    @forelse ($roles as $role)
        <tr>
            <td>{{ $role->name }}</td>
            <td><span class="panel-badge">{{ $role->guard_name }}</span></td>
            <td>
                @forelse ($role->permissions->take(4) as $permission)
                    <span class="panel-badge">{{ $permission->name }}</span>
                @empty
                    —
                @endforelse
                @if ($role->permissions->count() > 4)
                    <span class="text-muted">+{{ $role->permissions->count() - 4 }}</span>
                @endif
            </td>
            <x-table.actions
                :show="route('admin.roles.show', $role)"
                :edit="auth()->user()->can('edit-role') ? route('admin.roles.edit', $role) : null"
                :delete="auth()->user()->can('delete-role') ? route('admin.roles.destroy', $role) : null"
                confirm="Rol silinsin mi?"
                :confirm-text="$role->name.' ('.$role->guard_name.') silinecek. Bu role sahip kullanıcılar etkilenir.'"
            />
        </tr>
    @empty
        <x-table.empty colspan="4" icon="fa-solid fa-user-shield" title="Henüz rol yok." text="Kullanıcılara atamak için roller tanımlayın.">
            @can('create-role')
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.roles.create')">Yeni rol</x-ui.button>
            @endcan
        </x-table.empty>
    @endforelse
</tbody>
