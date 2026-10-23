<tbody>
    @forelse ($employees as $employee)
        <tr>
            <td>
                <div>{{ $employee->name }}</div>
                @if ($employee->party?->mobile || $employee->party?->phone)
                    <div class="text-sm text-slate-500">{{ $employee->party->mobile ?: $employee->party->phone }}</div>
                @endif
            </td>
            <td>{{ $employee->occupation?->label() ?: '—' }}</td>
            <td>{{ $employee->started_on?->format('d.m.Y') ?: '—' }}</td>
            <td class="is-num">{{ number_format((float) $employee->salary, 2, ',', '.') }} ₺</td>
            <td><x-ui.active-badge :active="$employee->active" /></td>
            <x-table.actions
                :show="route('admin.employees.show', $employee)"
                :edit="auth()->user()->can('edit-employee') ? route('admin.employees.edit', $employee) : null"
                :delete="auth()->user()->can('delete-employee') ? route('admin.employees.destroy', $employee) : null"
                confirm="Personel kartı kaldırılsın mı?"
                :confirm-text="$employee->name.' personel kartı silinecek ve Cari’deki Personel rolü kalkacak. Maaş/ödeme/prim varsa işlem reddedilir. Kişi cari kartı silinmez.'"
            />
        </tr>
    @empty
        @if (empty($filters))
            <x-table.empty colspan="6" icon="fa-solid fa-id-badge" title="Henüz personel kartı yok." text="Önce Cari → Kişiler’den kişi ekleyin, ardından personel kartı bağlayın.">
                <x-ui.button variant="create" icon="fa-solid fa-plus" :href="route('admin.employees.create')">Yeni personel kartı</x-ui.button>
            </x-table.empty>
        @else
            <x-table.no-results colspan="6" :reset="route('admin.employees.index')" />
        @endif
    @endforelse
</tbody>
