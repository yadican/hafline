@extends('admin.layouts.page')

@section('page')
    <div class="panel-wrap">
        <div class="panel">
            <div class="panel-title">
                <span>Örnek kayıtlar</span>
                <span class="panel-meta">Liste</span>
            </div>
            <div class="panel-body">
                <div class="panel-liste-bar">
                    <a href="{{ route('admin.examples.create') }}" class="panel-btn">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Yeni kayıt
                    </a>
                </div>
                <table class="panel-table panel-list">
                    <thead>
                        <tr>
                            <th>Başlık</th>
                            <th>Durum</th>
                            <th>Vardiya</th>
                            <th class="is-center">İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($examples as $example)
                            <tr>
                                <td>{{ $example->title }}</td>
                                <td>
                                    @if ($example->active)
                                        <span class="panel-badge panel-badge-aktif">{{ ['taslak' => 'Taslak', 'aktif' => 'Aktif', 'kapali' => 'Kapalı'][$example->status] ?? 'Aktif' }}</span>
                                    @else
                                        <span class="panel-badge panel-badge-pasif">Pasif</span>
                                    @endif
                                </td>
                                <td>{{ ['gunduz' => 'Gündüz', 'gece' => 'Gece'][$example->shift] ?? '—' }}</td>
                                <td class="panel-actions-cell">
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
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4">Henüz kayıt yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                {{ $examples->links('admin.partials.pager') }}
            </div>
        </div>
    </div>
@endsection
