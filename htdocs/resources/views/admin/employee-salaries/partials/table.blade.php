@php
    $fmt = fn (float $n): string => number_format($n, 2, ',', '.');
@endphp

<div class="report-toolbar no-print">
    <div>
        <h2 class="panel-subtitle">{{ $grid['period_label'] }}</h2>
        <p class="panel-hint">{{ count($grid['rows']) }} personel</p>
    </div>
    <div class="report-toolbar-actions">
        <x-ui.button icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
    </div>
</div>

<form method="POST" action="{{ route('admin.employee-salaries.store') }}" class="employee-salary-form">
    @csrf
    <input type="hidden" name="year" value="{{ $grid['year'] }}">
    <input type="hidden" name="month" value="{{ $grid['month'] }}">

    <x-table.list class="report-table">
        <thead>
            <tr>
                <th class="is-num">#</th>
                <th>Personel</th>
                <th>Görev</th>
                <th>İşe giriş</th>
                <th>İşten çıkış</th>
                <th class="is-num">Devreden</th>
                <th class="is-num">Maaş</th>
                <th class="is-num">Prim</th>
                <th class="is-num">Gün sayısı</th>
                <th class="is-num">Ödenecek</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($grid['rows'] as $index => $row)
                <tr>
                    <td class="is-num">{{ $index + 1 }}</td>
                    <td>{{ $row['name'] }}</td>
                    <td>{{ $row['occupation'] ?: '—' }}</td>
                    <td>{{ $row['started_on'] ? \Illuminate\Support\Carbon::parse($row['started_on'])->format('d.m.Y') : '—' }}</td>
                    <td>{{ $row['ended_on'] ? \Illuminate\Support\Carbon::parse($row['ended_on'])->format('d.m.Y') : '—' }}</td>
                    <td class="is-num">{{ $fmt($row['carry']) }}</td>
                    <td class="is-num">
                        <input type="hidden" name="rows[{{ $index }}][employee_id]" value="{{ $row['employee_id'] }}">
                        <input
                            type="number"
                            name="rows[{{ $index }}][base_salary]"
                            value="{{ old('rows.'.$index.'.base_salary', $row['base_salary']) }}"
                            step="0.01"
                            min="0"
                            class="panel-input"
                            required
                        >
                    </td>
                    <td class="is-num">{{ $fmt($row['bonus']) }}</td>
                    <td class="is-num">
                        <input
                            type="number"
                            name="rows[{{ $index }}][worked_days]"
                            value="{{ old('rows.'.$index.'.worked_days', $row['worked_days']) }}"
                            min="0"
                            max="31"
                            class="panel-input"
                            required
                        >
                    </td>
                    <td class="is-num">{{ $fmt($row['payable']) }} TL</td>
                </tr>
            @empty
                <x-table.empty colspan="10" title="Bu dönemde personel yok." text="Dönemi kapsayan açık iş dönemi olan personel listelenir." />
            @endforelse
        </tbody>
    </x-table.list>

    @if (count($grid['rows']) > 0)
        <div class="panel-liste-bar no-print mt-4">
            <x-ui.button type="submit" variant="create" icon="fa-solid fa-floppy-disk">Kaydet</x-ui.button>
        </div>
    @endif
</form>
