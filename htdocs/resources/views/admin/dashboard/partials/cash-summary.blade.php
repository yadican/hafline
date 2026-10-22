@if ($cashSummary !== null)
    <section class="dash-panel dash-panel-cash">
        <div class="dash-panel-head">
            <h2>Bu ay kasa</h2>
            <a href="{{ route($cashSummary['route']) }}" class="dash-panel-link">Rapor</a>
        </div>
        <p class="dash-panel-sub">{{ $cashSummary['period_label'] }}</p>

        <dl class="dash-cash">
            <div>
                <dt>Açılış</dt>
                <dd><x-ui.money :value="$cashSummary['opening']" /></dd>
            </div>
            <div class="is-in">
                <dt>Giriş</dt>
                <dd><x-ui.money :value="$cashSummary['total_giris']" /></dd>
            </div>
            <div class="is-out">
                <dt>Çıkış</dt>
                <dd><x-ui.money :value="$cashSummary['total_cikis']" /></dd>
            </div>
            <div class="is-close">
                <dt>Kapanış</dt>
                <dd><x-ui.money :value="$cashSummary['closing']" /></dd>
            </div>
        </dl>
    </section>
@endif
