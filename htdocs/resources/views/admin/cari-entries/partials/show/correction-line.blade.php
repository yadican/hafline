<div>
    @if ($line->party->trashed())
        {{ $line->party->name }}
    @else
        <a href="{{ route('admin.parties.show', $line->party) }}">{{ $line->party->name }}</a>
    @endif
    ·
    @if ((float) $line->debit)
        Borç <x-ui.money :value="$line->debit" />
    @else
        Alacak <x-ui.money :value="$line->credit" />
    @endif
</div>
