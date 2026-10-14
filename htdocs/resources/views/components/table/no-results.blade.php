@props(['colspan', 'reset'])

<x-table.empty
    :$colspan
    icon="fa-solid fa-magnifying-glass"
    title="Aramanızla eşleşen kayıt bulunamadı."
    text="Filtreleri değiştirip tekrar deneyin ya da temizleyip tüm listeye dönün."
>
    <x-ui.button icon="fa-solid fa-xmark" :href="$reset">Filtreleri temizle</x-ui.button>
</x-table.empty>
