@php
    $fmt = fn (float $n): string => number_format($n, 2, ',', '.');
@endphp

<div class="report-matrix">
    <div class="report-heading">
        <h2 class="report-title">{{ $grid['period_label'] }}</h2>
        <p class="report-subtitle">{{ count($grid['rows']) }} personel</p>
    </div>

    <form method="POST" action="{{ route('admin.employee-salaries.store') }}" class="employee-salary-form">
        @csrf
        <input type="hidden" name="year" value="{{ $grid['year'] }}">
        <input type="hidden" name="month" value="{{ $grid['month'] }}">

        <x-table.list class="report-table employee-salary-table">
            <thead>
                <tr class="report-col-head">
                    <th class="is-center">#</th>
                    <th>Personel</th>
                    <th>Görev</th>
                    <th>İşe giriş</th>
                    <th>İşten çıkış</th>
                    <th class="is-end">Devreden</th>
                    <th class="is-end">Maaş</th>
                    <th class="is-end">Prim</th>
                    <th class="is-end">Gün</th>
                    <th class="is-end">Ödenecek</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($grid['rows'] as $index => $row)
                    <tr>
                        <td class="is-center employee-salary-index">{{ $index + 1 }}</td>
                        <td class="employee-salary-name">{{ $row['name'] }}</td>
                        <td>{{ $row['occupation'] ?: '—' }}</td>
                        <td>{{ $row['started_on'] ? \Illuminate\Support\Carbon::parse($row['started_on'])->format('d.m.Y') : '—' }}</td>
                        <td>{{ $row['ended_on'] ? \Illuminate\Support\Carbon::parse($row['ended_on'])->format('d.m.Y') : '—' }}</td>
                        <td class="panel-tutar is-end">{{ $fmt($row['carry']) }}</td>
                        <td class="is-end employee-salary-edit">
                            <input type="hidden" name="rows[{{ $index }}][employee_id]" value="{{ $row['employee_id'] }}">
                            <input
                                type="number"
                                name="rows[{{ $index }}][base_salary]"
                                value="{{ old('rows.'.$index.'.base_salary', $row['base_salary']) }}"
                                step="0.01"
                                min="0"
                                class="slct panel-maas"
                                inputmode="decimal"
                                required
                            >
                        </td>
                        <td class="panel-tutar is-end">{{ $fmt($row['bonus']) }}</td>
                        <td class="is-end employee-salary-edit">
                            <input
                                type="number"
                                name="rows[{{ $index }}][worked_days]"
                                value="{{ old('rows.'.$index.'.worked_days', $row['worked_days']) }}"
                                min="0"
                                max="31"
                                class="slct"
                                inputmode="numeric"
                                required
                            >
                        </td>
                        <td class="panel-tutar is-end employee-salary-payable">{{ $fmt($row['payable']) }} ₺</td>
                    </tr>
                @empty
                    <x-table.empty colspan="10" title="Bu dönemde personel yok." text="Dönemi kapsayan açık iş dönemi olan personel listelenir." />
                @endforelse
            </tbody>
        </x-table.list>

        @if (count($grid['rows']) > 0)
            <div class="panel-liste-bar report-actions no-print">
                <x-ui.button type="submit" variant="create" icon="fa-solid fa-floppy-disk">Kaydet</x-ui.button>
                <x-ui.button type="button" icon="fa-solid fa-print" data-report-print>Yazdır</x-ui.button>
            </div>
        @endif
    </form>
</div>
