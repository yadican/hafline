@if ($stats === [])
    <div class="dash-empty">
        <p>Gösterilecek özet kartı yok. Yetkilerinize göre burada sayılar görünecek.</p>
    </div>
@else
    <div class="dash-stats">
        @foreach ($stats as $stat)
            @php
                $deltaClass = null;
                if ($stat['previous'] !== null) {
                    $deltaClass = match (true) {
                        $stat['value'] > $stat['previous'] => 'is-up',
                        $stat['value'] < $stat['previous'] => 'is-down',
                        default => 'is-flat',
                    };
                }
            @endphp
            <a href="{{ route($stat['route']) }}" class="dash-stat dash-tone-{{ $stat['tone'] }}" title="{{ $stat['hint'] }}">
                <span class="dash-stat-icon" aria-hidden="true">
                    <i class="{{ $stat['icon'] }}"></i>
                </span>
                <span class="dash-stat-body">
                    <span class="dash-stat-label">{{ $stat['label'] }}</span>
                    <span class="dash-stat-value">{{ number_format($stat['value'], 0, ',', '.') }}</span>
                    @if ($stat['previous'] !== null)
                        <span class="dash-stat-delta {{ $deltaClass }}">
                            {{ $stat['previous_label'] }} {{ number_format($stat['previous'], 0, ',', '.') }}
                        </span>
                    @endif
                </span>
            </a>
        @endforeach
    </div>
@endif
