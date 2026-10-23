@if ($party->sites->isNotEmpty())
    <div class="panel-form-grid panel-form-grid-after">
        <x-form.group legend="Şantiyeleri" icon="fa-solid fa-helmet-safety" description="Bu carinin çalıştığı şantiyeler; bağlantılar şantiye formundan yönetilir" class="is-wide">
            <table class="panel-subtable">
                <thead>
                    <tr>
                        <th>Şantiye</th>
                        <th>Rol</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($party->sites->groupBy('id') as $links)
                        @php($site = $links->first())
                        <tr>
                            <td>
                                @if ($site->trashed())
                                    {{ $site->name }}
                                @else
                                    <a href="{{ route('admin.sites.show', $site) }}">{{ $site->name }}</a>
                                @endif
                            </td>
                            <td>{{ $links->map(fn ($link) => \App\Enums\PartyRole::from($link->pivot->role)->label())->join(', ') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-form.group>
    </div>
@endif
