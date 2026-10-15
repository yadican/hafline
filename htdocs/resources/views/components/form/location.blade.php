{{-- $cities / $districts / $neighborhoods: id => ad; ilçe ve semt listeleri seçili üst kayda göre önceden doldurulur. --}}
@props(['cities' => [], 'districts' => [], 'neighborhoods' => []])

<div class="panel-contents" data-location>
    <x-form.select name="city_id" label="İl" :options="$cities" placeholder="İl seçin" searchable data-location-city />
    <x-form.select
        name="district_id"
        label="İlçe"
        :options="$districts"
        placeholder="İlçe seçin"
        searchable
        data-location-district
        :data-url="route('admin.locations.districts', ['city' => '__ID__'])"
    />
    <x-form.select
        name="neighborhood_id"
        label="Semt / mahalle"
        :options="$neighborhoods"
        placeholder="Semt seçin"
        searchable
        data-location-neighborhood
        :data-url="route('admin.locations.neighborhoods', ['district' => '__ID__'])"
    />
</div>
