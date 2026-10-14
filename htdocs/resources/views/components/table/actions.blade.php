@props([
    'edit' => null,
    'delete' => null,
    'confirm' => 'Kayıt silinsin mi?',
    'confirmText' => 'Kayıt listeden kaldırılacak ve geri alınamayacak.',
])

<td {{ $attributes->class(['panel-actions-cell']) }}>
    {{ $slot }}
    @if ($edit)
        <a href="{{ $edit }}" class="panel-act panel-act-edit" title="Düzenle">
            <i class="fa-solid fa-pen" aria-hidden="true"></i>
            <span class="sr-only">Düzenle</span>
        </a>
    @endif
    @if ($delete)
        <form method="POST" action="{{ $delete }}" class="js-confirm" data-confirm-title="{{ $confirm }}" data-confirm-text="{{ $confirmText }}" data-confirm-button="Sil" style="display:inline;">
            @csrf
            @method('DELETE')
            <button type="submit" class="panel-act panel-act-del" title="Sil">
                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                <span class="sr-only">Sil</span>
            </button>
        </form>
    @endif
</td>
