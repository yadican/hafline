@php($isPerson = $type === \App\Enums\PartyType::Person)

<tbody>
    @forelse ($parties as $party)
        <tr>
            <td>
                {{ $party->name }}
                @if ($party->isSystem())
                    <x-ui.badge icon="fa-solid fa-lock">Sistem</x-ui.badge>
                @endif            </td>
            <td>{{ ($isPerson ? $party->person?->national_id : $party->company?->tax_number) ?: '—' }}</td>
            <td>
                @if ($party->roles)
                    <span class="panel-chips">
                        @foreach ($party->roles as $role)
                            <span class="panel-chip">{{ $role->label() }}</span>
                        @endforeach
                    </span>
                @else
                    —
                @endif
            </td>
            <td>{{ $party->mobile ?: ($party->phone ?: '—') }}</td>
            <td class="is-center">
                @if ($party->vehicles_count)
                    <a href="{{ route('admin.vehicles.index', ['owner' => $party->id]) }}" title="Bu cariye kayıtlı araçları göster">{{ $party->vehicles_count }}</a>
                @else
                    —
                @endif
            </td>
            <td><x-ui.balance :value="$party->balance" /></td>
            <td><x-ui.active-badge :active="$party->active" /></td>
            <x-table.actions
                :show="route('admin.parties.show', $party)"
                :edit="auth()->user()->can('edit-party') ? route('admin.parties.edit', $party) : null"
                :delete="auth()->user()->can('delete-party') && ! $party->isSystem() ? route('admin.parties.destroy', $party) : null"
                confirm="Cari kart silinsin mi?"
                :confirm-text="$party->name.' listeden kaldırılacak. Aracı, döküm yeri veya cari hareketi varsa silme engellenir.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty
                colspan="8"
                :icon="$type->icon()"
                :title="$isPerson ? 'Henüz kişi yok.' : 'Henüz şirket yok.'"
                :text="$isPerson ? 'Araç sahiplerini, şoförleri ve personeli buradan ekleyin.' : 'Firmaları, döküm işletmecilerini ve akaryakıtçıları buradan ekleyin.'"
            >
                <x-ui.button variant="create" :icon="$isPerson ? 'fa-solid fa-user-plus' : 'fa-solid fa-building'" :href="route('admin.parties.create', ['type' => $type->value])">{{ $isPerson ? 'Yeni kişi' : 'Yeni şirket' }}</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="8" :reset="route($type->indexRoute())" />
        @endif
    @endforelse
</tbody>
