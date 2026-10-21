import $ from 'jquery';
import select2 from 'select2';

select2(window, $);
window.jQuery = window.$ = $;

const turkish = {
    errorLoading: () => 'Sonuçlar yüklenemedi',
    inputTooLong: ({ input, maximum }) => `${input.length - maximum} karakter daha silin`,
    inputTooShort: ({ input, minimum }) => `En az ${minimum - input.length} karakter daha girin`,
    loadingMore: () => 'Daha fazla yükleniyor…',
    maximumSelected: ({ maximum }) => `En fazla ${maximum} seçim yapabilirsiniz`,
    noResults: () => 'Sonuç bulunamadı',
    removeAllItems: () => 'Tüm seçimleri kaldır',
    removeItem: () => 'Kaldır',
    search: () => 'Ara',
    searching: () => 'Aranıyor…',
};

// select2 değişikliği jQuery üzerinden tetikler; addEventListener('change') dinleyenlerine
// ulaşması için seçimden sonra yerel bir change olayı da gönderilir.
function relayChange($select) {
    $select.on('select2:select select2:clear', () => {
        $select[0].dispatchEvent(new Event('change', { bubbles: true }));
    });
}

export function bindMultiSelects(root = document) {
    $(root).find('select[data-multi-select]').each(function () {
        const $select = $(this);

        if ($select.hasClass('select2-hidden-accessible')) {
            return;
        }

        $select.select2({
            width: '100%',
            closeOnSelect: false,
            placeholder: $select.data('placeholder') || 'Seçin',
            language: turkish,
        });
    });

    $(root).find('select[data-searchable-select]').each(function () {
        const $select = $(this);

        if ($select.hasClass('select2-hidden-accessible')) {
            return;
        }

        // Liste toolbar'ında native select gibi içerik genişliğinde kalsın;
        // form grid / haul-search gibi dolu satırlarda %100 kalsın.
        const inCompactToolbar = $select.closest('.panel-liste-bar').length > 0
            && $select.closest('.haul-search-bar, .panel-form-grid, .panel-arama-grid, .panel-field').length === 0;

        $select.select2({
            width: inCompactToolbar ? 'resolve' : '100%',
            placeholder: $select.data('placeholder') || 'Seçin',
            allowClear: ! $select.prop('required'),
            minimumResultsForSearch: $select.is('[data-hide-search]') ? Infinity : 0,
            language: turkish,
        });
        relayChange($select);
    });
}
