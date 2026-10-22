@if ($recentHauls !== null)
    <section class="dash-panel dash-panel-hauls">
        <div class="dash-panel-head">
            <h2>Bugünkü son taşımalar</h2>
            <a href="{{ route($recentHauls['route']) }}" class="dash-panel-link">Tümünü gör</a>
        </div>

        @if ($recentHauls['items'] === [])
            <p class="dash-panel-empty">Bugün henüz taşıma fişi yok.</p>
        @else
            <ul class="dash-list">
                @foreach ($recentHauls['items'] as $item)
                    <li>
                        <span class="dash-list-time">{{ $item['time'] }}</span>
                        <span class="dash-list-plate">{{ $item['plate'] }}</span>
                        <span class="dash-list-summary">{{ $item['summary'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endif
