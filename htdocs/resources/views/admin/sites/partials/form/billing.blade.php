<x-form.group legend="Şantiye carisi" icon="fa-solid fa-scale-balanced" description="Şantiyenin bize borçlandığı ve ödeme yaptığı cari">
    <x-form.select searchable name="party_id" label="Cari" placeholder="Cari seçin" :options="$siteParties" />
    <x-form.radios name="billing_mode" label="Faturalama" :options="\App\Enums\SiteBilling::options()" inline />
    <p class="panel-hint">Sefer başına: her taşıma fişi şantiye carisini güzergahtaki şantiye satış fiyatıyla borçlandırır. Hakediş: borç, Cari hareketlerinden girilen hakediş ile yazılır.</p>
</x-form.group>
