<tbody>
    @forelse ($examples as $example)
        <tr>
            <td>{{ $example->title }}</td>
            <td>{{ $example->email ?: '—' }}</td>
            <td>{{ $example->phone ?: '—' }}</td>
            <td>{{ $example->event_date?->format('d.m.Y') ?: '—' }}</td>
            <td>
                @if ($example->active)
                    <span class="panel-badge panel-badge-aktif">{{ $example->status?->label() ?? '—' }}</span>
                @else
                    <span class="panel-badge panel-badge-pasif">Pasif</span>
                @endif
            </td>
            <td>{{ $example->shift?->label() ?? '—' }}</td>
            <td class="panel-actions-cell">
                @include('admin.examples.partials.index.actions')
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="7">Henüz kayıt yok.</td>
        </tr>
    @endforelse
</tbody>
