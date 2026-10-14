<a href="{{ route('admin.hauls.edit', $haul) }}" class="panel-act panel-act-edit" title="Düzenle">
    <i class="fa-solid fa-pen" aria-hidden="true"></i>
    <span class="sr-only">Düzenle</span>
</a>
<form method="POST" action="{{ route('admin.hauls.destroy', $haul) }}" class="js-confirm" data-confirm-title="Taşıma fişi silinsin mi?" data-confirm-text="{{ $haul->ticket_no }} numaralı fişin bu seferi listeden kaldırılacak ve geri alınamayacak." data-confirm-button="Sil" style="display:inline;">
    @csrf
    @method('DELETE')
    <button type="submit" class="panel-act panel-act-del" title="Sil">
        <i class="fa-solid fa-trash" aria-hidden="true"></i>
        <span class="sr-only">Sil</span>
    </button>
</form>
