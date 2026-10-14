<tbody>
    @forelse ($examples as $example)
        <tr>
            <td>{{ $example->title }}</td>
            <td>{{ $example->email ?: '—' }}</td>
            <td>{{ $example->phone ?: '—' }}</td>
            <td>{{ $example->event_date?->format('d.m.Y') ?: '—' }}</td>
            <td>
                @if ($example->active)
                    <span class="panel-badge panel-badge-aktif">Aktif</span>
                @else
                    <span class="panel-badge panel-badge-pasif">Pasif</span>
                @endif
            </td>
            <td>
                @if ($example->shift)
                    <span class="panel-badge {{ $example->shift->badge() }}" title="{{ $example->shift->label() }}">
                        <i class="{{ $example->shift->icon() }}" aria-hidden="true"></i>
                        <span class="sr-only">{{ $example->shift->label() }}</span>
                    </span>
                @else
                    —
                @endif
            </td>
            <td class="panel-actions-cell">
                @include('admin.examples.partials.index.actions')
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="7">{{ empty($filters) ? 'Henüz kayıt yok.' : 'Aramanızla eşleşen kayıt bulunamadı.' }}</td>
        </tr>
    @endforelse
</tbody>
