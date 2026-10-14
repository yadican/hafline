<tbody>
    @forelse ($examples as $example)
        <tr>
            <td>{{ $example->title }}</td>
            <td>{{ $example->email ?: '—' }}</td>
            <td>{{ $example->phone ?: '—' }}</td>
            <td>{{ $example->event_date?->format('d.m.Y') ?: '—' }}</td>
            <td><x-ui.active-badge :active="$example->active" /></td>
            <td><x-ui.enum-badge :value="$example->shift" /></td>
            <x-table.actions
                :edit="route('admin.examples.edit', $example)"
                :delete="route('admin.examples.destroy', $example)"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="7" title="Henüz kayıt yok." text="İlk kaydı oluşturarak başlayın.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.examples.create')">Yeni kayıt</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="7" :reset="route('admin.examples.index')" />
        @endif
    @endforelse
</tbody>
