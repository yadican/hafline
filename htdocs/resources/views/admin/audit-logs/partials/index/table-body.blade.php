<tbody>
    @forelse ($logs as $log)
        <tr>
            <td>{{ $log->created_at?->format('d.m.Y H:i:s') ?: '—' }}</td>
            <td>{{ $log->user?->name ?: '—' }}</td>
            <td>{{ $log->actionLabel() }}</td>
            <td>{{ $log->method ?: '—' }}</td>
            <td class="truncate max-w-xs" title="{{ $log->route }}">{{ $log->route ?: '—' }}</td>
            <td>{{ $log->ip_address ?: '—' }}</td>
            <td class="is-center">{{ $log->status ?: '—' }}</td>
            <x-table.actions>
                <a href="{{ route($showRoute, $log) }}" class="panel-act panel-act-show" title="Detay">
                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                    <span class="sr-only">Detay</span>
                </a>
            </x-table.actions>
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="8" title="Henüz log yok." text="İşlemler kaydedildikçe burada listelenir." />
        @else
            <x-table.no-results colspan="8" :reset="route($indexRoute)" />
        @endif
    @endforelse
</tbody>
