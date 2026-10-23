<tbody>
    @forelse ($works as $work)
        <tr>
            <td class="is-center">{{ $works->firstItem() + $loop->index }}</td>
            <td>{{ $work->ticket_no }}</td>
            <td class="panel-plaka-cell">{{ $work->vehicle?->plate }}</td>
            <td>{{ $work->party?->name }}</td>
            <td>{{ $work->site?->name }}</td>
            <td @if ($work->operator_amount !== null) title="Operatöre alacak: {{ number_format((float) $work->operator_amount, 2, ',', '.') }} TL" @endif>
                {{ $work->operator?->name ?? '—' }}@if ($work->operator_amount !== null) · {{ number_format((float) $work->operator_amount, 2, ',', '.') }} TL @endif
            </td>
            <td class="is-center">{{ \App\Models\MachineWork::formatHours($work->hours) }}</td>
            <td class="is-end">{{ number_format((float) $work->hourly_rate, 2, ',', '.') }} TL</td>
            <td class="panel-tutar">{{ number_format((float) $work->amount, 2, ',', '.') }} TL</td>
            <td>{{ $work->worked_on->translatedFormat('j.m.Y') }}</td>
            <td class="is-center"><x-ui.enum-badge :value="$work->shift" /></td>
            <x-table.actions
                :show="route('admin.machine-works.show', $work)"
                :edit="auth()->user()->can('edit-machine-work') ? route('admin.machine-works.edit', $work) : null"
                :delete="auth()->user()->can('delete-machine-work') ? route('admin.machine-works.destroy', $work) : null"
                confirm="Makine çalışma fişi silinsin mi?"
                :confirm-text="$work->ticket_no.' numaralı fiş silinecek; makine sahibinin carisindeki alacak ters kayıtla kapatılır.'"
            />
        </tr>
    @empty
        @if ($filters === [])
            <x-table.empty
                colspan="14"
                icon="fa-solid fa-person-digging"
                :title="'Son '.\App\Repositories\MachineWorkRepository::RECENT_DAYS.' günde girilmiş makine çalışma fişi yok.'"
                text="Daha eski fişler için Arama panelinden tarih aralığı seçin."
            >
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.machine-works.create')">Yeni fiş</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="14" :reset="route('admin.machine-works.index')" />
        @endif
    @endforelse

    @if ($works->isNotEmpty())
        <tr class="toplam-satir">
            <td colspan="6" class="panel-dip-cell">
                <span class="panel-dip-label">Toplam · {{ number_format($works->total(), 0, ',', '.') }} fiş</span>
            </td>
            <td class="is-center">{{ \App\Models\MachineWork::formatHours($totals['hours']) }}</td>
            <td></td>
            <td class="panel-tutar">{{ number_format($totals['amount'], 2, ',', '.') }} TL</td>
            <td colspan="5"></td>
        </tr>
    @endif
</tbody>
