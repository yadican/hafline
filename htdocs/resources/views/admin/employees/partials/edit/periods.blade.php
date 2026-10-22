<div class="panel-card mt-6">
    <h2 class="panel-subtitle">İş dönemleri</h2>
    <x-table.list>
        <thead>
            <tr>
                <th>Giriş</th>
                <th>Çıkış</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($employee->periods as $period)
                <tr>
                    <td>{{ $period->started_on?->format('d.m.Y') ?: '—' }}</td>
                    <td>{{ $period->ended_on?->format('d.m.Y') ?: 'Devam ediyor' }}</td>
                </tr>
            @empty
                <x-table.empty colspan="2" title="Dönem kaydı yok." />
            @endforelse
        </tbody>
    </x-table.list>
</div>
