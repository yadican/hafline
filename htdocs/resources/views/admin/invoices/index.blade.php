@extends('admin.layouts.app')

@section('content')
    <x-panel.page
        class="haul-list"
        heading="Faturalar"
        icon="fa-solid fa-file-invoice"
        description="Alış, satış ve alış iadesi faturaları. Alış tedarikçiye alacak, satış müşteriye borç, alış iadesi satıcıya borç yazar."
        :breadcrumbs="['Gösterge paneli' => route('admin.dashboard'), 'Finans' => null, 'Faturalar' => null]"
    >
        <div x-data="{ searchOpen: false }">
            <div class="panel-liste-bar haul-toolbar">
                <div class="haul-toolbar-row">
                    <div class="haul-toolbar-start">
                        <x-ui.button
                            variant="search"
                            icon="fa-solid fa-filter"
                            class="haul-search-toggle"
                            x-bind:class="{ 'is-open': searchOpen }"
                            x-on:click="searchOpen = ! searchOpen"
                            x-bind:aria-expanded="searchOpen.toString()"
                            aria-controls="invoice-search"
                            aria-expanded="false"
                        >
                            Arama
                            @if ($filters !== [])
                                <span class="haul-search-count">{{ count($filters) }}</span>
                            @endif
                            <i class="fa-solid fa-chevron-down haul-search-chevron" aria-hidden="true"></i>
                        </x-ui.button>
                        @if ($filters !== [])
                            <x-ui.button icon="fa-solid fa-xmark" :href="route('admin.invoices.index')">Filtreleri temizle</x-ui.button>
                        @endif
                    </div>
                    <div class="haul-toolbar-end">
                        <span class="haul-toolbar-count">{{ number_format($invoices->total(), 0, ',', '.') }} kayıt</span>
                        @can('create-invoice')
                            <x-ui.button variant="create" icon="fa-solid fa-file-invoice" :href="route('admin.invoices.create')">Yeni fatura</x-ui.button>
                        @endcan
                    </div>
                </div>
            </div>

            @php
                $dueBase = collect($filters)->except(['overdue', 'due_from', 'due_to'])->all();
                $dueFilters = collect($filters)->only(['overdue', 'due_from', 'due_to'])->all();
                $activeDue = collect($options['due_presets'])
                    ->first(fn (array $preset): bool => $dueFilters == $preset['filters'])['key'] ?? null;
            @endphp
            <nav class="panel-ledger-modules" aria-label="Vade filtreleri">
                @foreach ($options['due_presets'] as $preset)
                    <a
                        href="{{ route('admin.invoices.index', ['f' => [...$dueBase, ...$preset['filters']]]) }}"
                        @class(['panel-chip', 'is-active' => $activeDue === $preset['key']])
                    >{{ $preset['label'] }}</a>
                @endforeach
            </nav>

            <div id="invoice-search" class="panel-liste-bar haul-search-bar" x-show="searchOpen" x-cloak>
                <x-search.form :action="route('admin.invoices.index')">
                    <x-search.select name="f[direction]" label="Yön" placeholder="Alış ve satış" :options="$options['directions']" :value="$filters['direction'] ?? null" class="md:col-span-3" />
                    <x-search.select searchable name="f[party]" label="Cari" placeholder="Tüm cariler" :options="$options['parties']" :value="$filters['party'] ?? null" class="md:col-span-4" />
                    <x-search.select name="f[draft]" label="Durum" placeholder="Tümü" :options="$options['drafts']" :value="$filters['draft'] ?? null" class="md:col-span-2" />
                    <x-form.input name="f[from]" label="" type="date" :value="$filters['from'] ?? null" aria-label="Tarih (başlangıç)" class="md:col-span-2" />
                    <x-form.input name="f[to]" label="" type="date" :value="$filters['to'] ?? null" aria-label="Tarih (bitiş)" class="md:col-span-1" />
                    <x-form.input name="f[due_from]" label="" type="date" :value="$filters['due_from'] ?? null" aria-label="Vade başlangıç" class="md:col-span-3" />
                    <x-form.input name="f[due_to]" label="" type="date" :value="$filters['due_to'] ?? null" aria-label="Vade bitiş" class="md:col-span-3" />
                    @if (($filters['overdue'] ?? null) === '1')
                        <input type="hidden" name="f[overdue]" value="1">
                    @endif
                    <div class="col-span-full mt-1 flex flex-wrap justify-stretch gap-2 border-t border-[var(--ui-border-soft)] pt-3 sm:justify-end">
                        <x-ui.button type="submit" variant="search" icon="fa-solid fa-magnifying-glass" class="w-full justify-center sm:w-auto">Ara</x-ui.button>
                    </div>
                </x-search.form>
            </div>
        </div>

        <x-table.list class="haul-table">
            <thead>
                <tr>
                    <x-table.th sort="issued_on" first="desc">Tarih</x-table.th>
                    <x-table.th sort="invoice_no">Fatura no</x-table.th>
                    <th>Yön</th>
                    <x-table.th sort="party">Cari</x-table.th>
                    <x-table.th sort="total" first="desc">Toplam</x-table.th>
                    <th class="is-end">Brüt kâr</th>
                    <th class="is-end">Ödenen</th>
                    <th class="is-end">Kalan</th>
                    <x-table.th sort="due_on">Vade</x-table.th>
                    <th>Durum</th>
                    <th class="is-center is-actions">İşlem</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoices as $invoice)
                    @php
                        $status = $invoice->paymentStatus();
                        $grossProfit = $invoice->grossProfit();
                    @endphp
                    <tr>
                        <td>{{ $invoice->issued_on->format('d.m.Y') }}</td>
                        <td>
                            {{ $invoice->invoice_no }}
                            @if ($invoice->attachments_count > 0)
                                <i class="fa-solid fa-paperclip" title="{{ $invoice->attachments_count }} dosya" aria-hidden="true"></i>
                                <span class="sr-only">{{ $invoice->attachments_count }} dosya</span>
                            @endif
                        </td>
                        <td>{{ $invoice->direction->label() }}</td>
                        <td>
                            {{ $invoice->party?->name }}
                            @if ($invoice->vehicle)
                                <span class="panel-plate-text">· {{ $invoice->vehicle->plate }}</span>
                            @endif
                        </td>
                        <td class="panel-tutar">{{ number_format((float) $invoice->total, 2, ',', '.') }} TL</td>
                        <td class="panel-tutar">{{ $grossProfit !== null ? number_format($grossProfit, 2, ',', '.').' TL' : '—' }}</td>
                        <td class="panel-tutar">{{ number_format($invoice->paidAmount(), 2, ',', '.') }} TL</td>
                        <td class="panel-tutar">{{ number_format($invoice->remainingAmount(), 2, ',', '.') }} TL</td>
                        <td>{{ $invoice->due_on?->format('d.m.Y') ?? '—' }}</td>
                        <td>
                            @if ($invoice->isDraft())
                                <x-ui.badge tone="pasif" icon="fa-solid fa-file">Taslak</x-ui.badge>
                            @else
                                <x-ui.badge :tone="$status->tone()">{{ $status->label($invoice->direction) }}</x-ui.badge>
                                @if ($invoice->isOverdue())
                                    <x-ui.badge tone="gider" icon="fa-solid fa-clock">Vadesi geçti</x-ui.badge>
                                @endif
                            @endif
                        </td>
                        <x-table.actions
                            :show="route('admin.invoices.show', $invoice)"
                            :edit="auth()->user()->can('edit-invoice') ? route('admin.invoices.edit', $invoice) : null"
                            :delete="$invoice->paidAmount() <= 0 && auth()->user()->can('delete-invoice') ? route('admin.invoices.destroy', $invoice) : null"
                            confirm="Fatura silinsin mi?"
                            :confirm-text="$invoice->isDraft() ? 'Taslak silinir; cariye yazılmamıştı.' : 'Cariye yazılan satır ters kayıtla kapatılır.'"
                        />
                    </tr>
                @empty
                    @if ($filters === [])
                        <x-table.empty colspan="11" icon="fa-solid fa-file-invoice" title="Henüz fatura yok." />
                    @else
                        <x-table.no-results colspan="11" :reset="route('admin.invoices.index')" />
                    @endif
                @endforelse
            </tbody>
        </x-table.list>
        <x-table.pager :items="$invoices" />
    </x-panel.page>
@endsection
