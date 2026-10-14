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
            <td>{{ $haul->hauled_at->translatedFormat('j F Y H:i') }}</td>
            <td>{{ $haul->created_at->translatedFormat('j F Y H:i') }}</td>
            <td>{{ $haul->creator?->name ?? '—' }}</td>
            <td class="is-center">
                <span class="panel-badge {{ $haul->shift->badge() }}" title="{{ $haul->shift->label() }}">
                    <i class="{{ $haul->shift->icon() }}" aria-hidden="true"></i>
                    <span class="sr-only">{{ $haul->shift->label() }}</span>
                </span>
            </td>
            <td class="panel-actions-cell">
                @include('admin.hauls.partials.index.actions')
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="14">
                {{ $filters === [] ? 'Son '.\App\Repositories\HaulRepository::RECENT_DAYS.' günde girilmiş taşıma fişi yok.' : 'Aramanızla eşleşen kayıt bulunamadı.' }}
            </td>
        </tr>
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
