<div class="dash-stats">
    @foreach ($stats as $stat)
        <a href="{{ route($stat['route']) }}" class="dash-stat" title="{{ $stat['hint'] }}">
            <span class="dash-stat-icon" aria-hidden="true">
                <i class="{{ $stat['icon'] }}"></i>
            </span>
            <span>
                <span class="dash-stat-label">{{ $stat['label'] }}</span>
                <span class="dash-stat-value">{{ number_format($stat['value'], 0, ',', '.') }}</span>
            </span>
        </a>
    @endforeach
</div>
