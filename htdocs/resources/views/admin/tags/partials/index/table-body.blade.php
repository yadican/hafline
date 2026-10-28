<tbody>
    @forelse ($tags as $tag)
        <tr>
            <td><x-ui.tag :tag="$tag" /></td>
            <td class="is-center">{{ $tag->parties_count ?: '—' }}</td>
            <td><x-ui.active-badge :active="$tag->active" /></td>
            <x-table.actions
                :show="route('admin.tags.show', $tag)"
                :edit="auth()->user()->can('edit-tag') ? route('admin.tags.edit', $tag) : null"
                :delete="auth()->user()->can('delete-tag') ? route('admin.tags.destroy', $tag) : null"
                confirm="Etiket silinsin mi?"
                :confirm-text="$tag->name.' etiketi silinecek ve bağlı olduğu cari kartlardan kaldırılacak.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="4" icon="fa-solid fa-tag" title="Henüz etiket yok." text="Cari kartları gruplamak için ilk etiketi ekleyin.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.tags.create')">Yeni etiket</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="4" :reset="route('admin.tags.index')" />
        @endif
    @endforelse
</tbody>
