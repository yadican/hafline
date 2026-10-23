<tbody>
    @forelse ($users as $user)
        <tr>
            <td>{{ $user->name }}</td>
            <td>{{ $user->username }}</td>
            <td>{{ $user->email }}</td>
            <td>
                @forelse ($user->roles as $role)
                    <span class="panel-badge">{{ $role->name }}</span>
                @empty
                    —
                @endforelse
            </td>
            <td><x-ui.active-badge :active="$user->active" /></td>
            <x-table.actions
                :show="route('admin.users.show', $user)"
                :edit="auth()->user()->can('edit-user') ? route('admin.users.edit', $user) : null"
                :delete="auth()->user()->can('delete-user') ? route('admin.users.destroy', $user) : null"
                confirm="Kullanıcı silinsin mi?"
                :confirm-text="$user->name.' paneldan kaldırılacak.'"
            />
        </tr>
    @empty
        <x-table.empty colspan="6" icon="fa-solid fa-users" title="Henüz kullanıcı yok." text="Yönetim paneli için kullanıcı ekleyin.">
            @can('create-user')
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.users.create')">Yeni kullanıcı</x-ui.button>
            @endcan
        </x-table.empty>
    @endforelse
</tbody>
