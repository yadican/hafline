@props([
    'show' => null,
    'edit' => null,
    'delete' => null,
    'confirm' => 'Kayıt silinsin mi?',
    'confirmText' => 'Kayıt listeden kaldırılacak ve geri alınamayacak.',
])

<td {{ $attributes->class(['panel-actions-cell']) }}>
    <span class="panel-actions">
        {{ $slot }}
        @if ($show)
            <a href="{{ $show }}" class="panel-act panel-act-show" title="Detay">
                <i class="fa-solid fa-eye" aria-hidden="true"></i>
                <span class="sr-only">Detay</span>
            </a>
        @endif
        @if ($edit)
            <a href="{{ $edit }}" class="panel-act panel-act-edit" title="Düzenle">
                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                <span class="sr-only">Düzenle</span>
            </a>
        @endif
        @if ($delete)
            <form method="POST" action="{{ $delete }}" class="js-confirm panel-actions-form" data-confirm-title="{{ $confirm }}" data-confirm-text="{{ $confirmText }}" data-confirm-button="Sil">
                @csrf
                @method('DELETE')
                <button type="submit" class="panel-act panel-act-del" title="Sil">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    <span class="sr-only">Sil</span>
                </button>
            </form>
        @endif
    </span>
</td>
