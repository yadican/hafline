<tbody>
    @forelse ($groups as $group)
        <tr>
            <td>{{ $group->name }}</td>
            <td class="is-center">
                @if ($group->companies_count)
                    <a href="{{ route('admin.companies.index', ['group' => $group->id]) }}" title="Bu gruptaki şirketleri göster">{{ $group->companies_count }}</a>
                @else
                    —
                @endif
            </td>
            <td><x-ui.active-badge :active="$group->active" /></td>
            <x-table.actions
                :show="route('admin.company-groups.show', $group)"
                :edit="auth()->user()->can('edit-company-group') ? route('admin.company-groups.edit', $group) : null"
                :delete="auth()->user()->can('delete-company-group') ? route('admin.company-groups.destroy', $group) : null"
                confirm="Şirket grubu silinsin mi?"
                :confirm-text="$group->name.' grubu listeden kaldırılacak. Grupta şirket varsa silme engellenir.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="4" icon="fa-solid fa-sitemap" title="Henüz şirket grubu yok." text="Birlikte izlediğiniz şirketleri bir grupta toplayın.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.company-groups.create')">Yeni grup</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="4" :reset="route('admin.company-groups.index')" />
        @endif
    @endforelse
</tbody>
