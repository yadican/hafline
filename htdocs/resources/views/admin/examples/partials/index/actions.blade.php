<a href="{{ route('admin.examples.edit', $example) }}" class="panel-act panel-act-edit" title="Düzenle">
    <i class="fa-solid fa-pen" aria-hidden="true"></i>
    <span class="sr-only">Düzenle</span>
</a>
<form method="POST" action="{{ route('admin.examples.destroy', $example) }}" style="display:inline;" onsubmit="return confirm('Bu kayıt silinsin mi?')">
    @csrf
    @method('DELETE')
    <button type="submit" class="panel-act panel-act-del" title="Sil">
        <i class="fa-solid fa-trash" aria-hidden="true"></i>
        <span class="sr-only">Sil</span>
    </button>
</form>
