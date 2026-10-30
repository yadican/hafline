@if ($financeReminders !== null)
    <section class="dash-panel dash-panel-finance" x-data="{ tab: @js($financeReminders['active']) }">
        <div class="dash-panel-head">
            <h2><i class="fa-solid fa-bell" aria-hidden="true"></i> Finans hatırlatıcıları</h2>
            <a href="{{ route($financeReminders['route']) }}" class="dash-panel-link">Çek / Senet</a>
        </div>
        <p class="dash-panel-sub">Vadesi gelen ve yaklaşan, henüz tahsil edilmemiş ya da ödenmemiş çek / senetler.</p>

        <div class="dash-tabs" role="tablist">
            @foreach ($financeReminders['tabs'] as $tab)
                <button
                    type="button"
                    role="tab"
                    id="dash-finance-tab-{{ $tab['key'] }}"
                    aria-controls="dash-finance-panel-{{ $tab['key'] }}"
                    @class(['dash-tab', 'dash-tab-'.$tab['key'], 'is-active' => $tab['key'] === $financeReminders['active']])
                    :class="{ 'is-active': tab === @js($tab['key']) }"
                    :aria-selected="tab === @js($tab['key'])"
                    @click="tab = @js($tab['key'])"
                >
                    {{ $tab['label'] }}
                    <span class="dash-tab-count">{{ $tab['count'] }}</span>
                </button>
            @endforeach
        </div>

        @foreach ($financeReminders['tabs'] as $tab)
            <div
                role="tabpanel"
                id="dash-finance-panel-{{ $tab['key'] }}"
                aria-labelledby="dash-finance-tab-{{ $tab['key'] }}"
                class="dash-tab-panel"
                x-show="tab === @js($tab['key'])"
                @if ($tab['key'] !== $financeReminders['active']) x-cloak @endif
            >
                @if ($tab['count'] === 0)
                    <p class="dash-panel-empty">{{ $tab['empty'] }}</p>
                @else
                    <div class="dash-due-totals">
                        <span class="is-in">Tahsil edilecek <x-ui.money :value="$tab['received']" /></span>
                        <span class="is-out">Ödenecek <x-ui.money :value="$tab['given']" /></span>
                    </div>

                    <ul class="dash-due-list">
                        @foreach ($tab['items'] as $item)
                            <li>
                                <a href="{{ route('admin.financial-instruments.show', $item['id']) }}" @class(['dash-due-item', 'is-'.$item['direction']])>
                                    <span @class(['dash-due-date', 'is-overdue' => $item['is_overdue']])>
                                        <strong>{{ $item['due_on'] }}</strong>
                                        <small>{{ $item['when'] }}</small>
                                    </span>
                                    <span class="dash-due-party">
                                        <strong>{{ $item['party'] }}</strong>
                                        <small>
                                            <i class="{{ $item['method_icon'] }}" aria-hidden="true"></i>
                                            {{ $item['method'] }}{{ $item['number'] ? ' · No '.$item['number'] : '' }}
                                        </small>
                                    </span>
                                    <span class="dash-due-amount">
                                        <x-ui.money :value="$item['amount']" />
                                        <small>{{ $item['direction_label'] }}</small>
                                    </span>
                                </a>
                            </li>
                        @endforeach
                    </ul>

                    @if ($tab['count'] > count($tab['items']))
                        <a href="{{ route('admin.financial-instruments.index', $tab['filters']) }}" class="dash-due-more">
                            Tümünü gör ({{ $tab['count'] }})
                        </a>
                    @endif
                @endif
            </div>
        @endforeach
    </section>
@endif
