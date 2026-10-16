<tbody>
    @forelse ($hauls as $haul)
        <tr>
            <td class="is-center">{{ $hauls->firstItem() + $loop->index }}</td>
            <td>{{ $haul->ticket_no }}</td>
            <td>{{ $haul->driver_name ?: '—' }}</td>
            <td class="panel-plaka-cell">{{ $haul->vehicle?->plate }}</td>
            <td>{{ $haul->vehicle?->vehicleClass?->name ?? '—' }}</td>
            <td>{{ $haul->site?->name }}</td>
            <td>{{ $haul->dumpSite?->name }}</td>
            <td class="panel-tutar">{{ number_format((float) $haul->price, 2, ',', '.') }} TL</td>
            <td class="is-center">{{ $haul->tonnage }}</td>
            <td>{{ $haul->hauled_at->translatedFormat('d.m.Y H:i') }}</td>
            <td>{{ $haul->created_at->translatedFormat('d.m.Y H:i') }}</td>
            <td>{{ $haul->creator?->name ?? '—' }}</td>
            <td class="is-center"><x-ui.enum-badge :value="$haul->shift" /></td>
            <x-table.actions
                :edit="route('admin.hauls.edit', $haul)"
                :delete="route('admin.hauls.destroy', $haul)"
                confirm="Taşıma fişi silinsin mi?"
                :confirm-text="$haul->ticket_no.' numaralı fişin bu seferi listeden kaldırılacak ve geri alınamayacak.'"
            />
        </tr>
    @empty
        @if ($filters === [])
            <x-table.empty
                colspan="14"
                icon="fa-solid fa-truck-ramp-box"
                :title="'Son '.\App\Repositories\HaulRepository::RECENT_DAYS.' günde girilmiş taşıma fişi yok.'"
                text="Daha eski fişler için Arama panelinden tarih aralığı seçin."
            >
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.hauls.create')">Yeni fiş</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="14" :reset="route('admin.hauls.index')" />
        @endif
    @endforelse

    @if ($hauls->isNotEmpty())
        <tr class="toplam-satir">
            <td colspan="7" class="panel-dip-cell">
                <span class="panel-dip-label">Toplam · {{ number_format($hauls->total(), 0, ',', '.') }} sefer</span>
            </td>
            <td class="panel-tutar">{{ number_format($totals['price'], 2, ',', '.') }} TL</td>
            <td class="is-center">{{ number_format($totals['tonnage'], 0, ',', '.') }}</td>
            <td colspan="5"></td>
        </tr>
    @endif
</tbody>
