@if ($employee->periods->isNotEmpty())
    <table class="panel-subtable">
        <thead>
            <tr>
                <th>Giriş</th>
                <th>Çıkış</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($employee->periods as $period)
                <tr>
                    <td>{{ $period->started_on?->format('d.m.Y') ?: '—' }}</td>
                    <td>{{ $period->ended_on?->format('d.m.Y') ?: 'Devam ediyor' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@else
    <p class="panel-hint">Dönem kaydı yok.</p>
@endif
