@if ($invoiceReminders !== null)
    @foreach (['receivables' => $invoiceReminders['receivables'], 'payables' => $invoiceReminders['payables']] as $panel)
        <section class="dash-panel dash-panel-finance dash-panel-invoice" x-data="{ tab: @js($panel['active']) }">
            <div class="dash-panel-head">
                <h2><i class="fa-solid fa-{{ $panel['key'] === 'receivables' ? 'arrow-trend-up' : 'arrow-trend-down' }}" aria-hidden="true"></i> {{ $panel['label'] }}</h2>
                <a href="{{ route($panel['route'], ['f' => ['direction' => $panel['key'] === 'receivables' ? 'sale' : 'purchase']]) }}" class="dash-panel-link">Faturalar</a>
            </div>
            <p class="dash-panel-sub">
                {{ $panel['key'] === 'receivables' ? 'Vadesi girilmiş, kalanı olan satış faturaları.' : 'Vadesi girilmiş, kalanı olan alış faturaları.' }}
            </p>

            <div class="dash-tabs" role="tablist">
                @foreach ($panel['tabs'] as $tab)
                    <button
                        type="button"
                        role="tab"
                        id="dash-invoice-{{ $panel['key'] }}-tab-{{ $tab['key'] }}"
                        aria-controls="dash-invoice-{{ $panel['key'] }}-panel-{{ $tab['key'] }}"
                        @class(['dash-tab', 'dash-tab-'.$tab['key'], 'is-active' => $tab['key'] === $panel['active']])
                        :class="{ 'is-active': tab === @js($tab['key']) }"
                        :aria-selected="tab === @js($tab['key'])"
                        @click="tab = @js($tab['key'])"
                    >
                        {{ $tab['label'] }}
                        <span class="dash-tab-count">{{ $tab['count'] }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($panel['tabs'] as $tab)
                <div
                    role="tabpanel"
                    id="dash-invoice-{{ $panel['key'] }}-panel-{{ $tab['key'] }}"
                    aria-labelledby="dash-invoice-{{ $panel['key'] }}-tab-{{ $tab['key'] }}"
                    class="dash-tab-panel"
                    x-show="tab === @js($tab['key'])"
                    @if ($tab['key'] !== $panel['active']) x-cloak @endif
                >
                    @if ($tab['count'] === 0)
                        <p class="dash-panel-empty">{{ $tab['empty'] }}</p>
                    @else
                        <div class="dash-due-totals">
                            <span @class([$panel['key'] === 'receivables' ? 'is-in' : 'is-out'])>
                                {{ $panel['key'] === 'receivables' ? 'Tahsil edilecek' : 'Ödenecek' }}
                                <x-ui.money :value="$tab['amount']" />
                            </span>
                        </div>

                        <ul class="dash-due-list">
                            @foreach ($tab['items'] as $item)
                                <li class="dash-due-row">
                                    <a href="{{ route('admin.invoices.show', $item['id']) }}" @class(['dash-due-item', $panel['key'] === 'receivables' ? 'is-received' : 'is-given'])>
                                        <span @class(['dash-due-date', 'is-overdue' => $item['is_overdue']])>
                                            <strong>{{ $item['due_on'] }}</strong>
                                            <small>{{ $item['when'] }}</small>
                                        </span>
                                        <span class="dash-due-party">
                                            <strong>{{ $item['party'] }}</strong>
                                            <small>No {{ $item['number'] }}</small>
                                        </span>
                                        <span class="dash-due-amount">
                                            <x-ui.money :value="$item['amount']" />
                                            <small>Kalan</small>
                                        </span>
                                    </a>
                                    @can('edit-invoice')
                                        <form method="POST" action="{{ route('admin.invoices.reminder.dismiss', $item['id']) }}" class="dash-due-dismiss">
                                            @csrf
                                            <button type="submit" title="Hatırlatmayı kapat">Hatırlatmayı kapat</button>
                                        </form>
                                    @endcan
                                </li>
                            @endforeach
                        </ul>

                        @if ($tab['count'] > 0)
                            <a href="{{ route($panel['route'], ['f' => $tab['filters']]) }}" class="dash-due-more">
                                Tümünü gör ({{ $tab['count'] }})
                            </a>
                        @endif
                    @endif
                </div>
            @endforeach
        </section>
    @endforeach
@endif
