<div class="panel-form-grid panel-form-grid-after">
    <x-form.group legend="Resimler" icon="fa-solid fa-camera" description="Çekin / senedin ön ve arka yüzü, ciro yazısı" class="is-wide">
        @if ($instrument->photos->isNotEmpty())
            <div class="flex flex-wrap gap-3">
                @foreach ($instrument->photos as $photo)
                    @php($photoUrl = route('admin.financial-instruments.photos.show', [$instrument, $photo]))
                    <div class="flex flex-col items-start gap-1">
                        <a href="{{ $photoUrl }}" target="_blank" rel="noopener" class="inline-block">
                            <img
                                src="{{ $photoUrl }}"
                                alt="{{ $instrument->payment_method->label() }} resmi {{ $loop->iteration }}"
                                class="max-h-48 max-w-full rounded border border-[var(--ui-border-soft)] object-contain"
                            >
                        </a>
                        @can('edit-financial-instrument')
                            <form method="POST" action="{{ route('admin.financial-instruments.photos.destroy', [$instrument, $photo]) }}" class="js-confirm" data-confirm-title="Resim silinsin mi?" data-confirm-text="Resim bu kayıttan kaldırılır." data-confirm-button="Sil">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="panel-act panel-act-del" title="Resmi sil">
                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    <span class="sr-only">Resmi sil</span>
                                </button>
                            </form>
                        @endcan
                    </div>
                @endforeach
            </div>
        @else
            <p class="panel-hint">Henüz resim yok.</p>
        @endif

        @can('edit-financial-instrument')
            <form method="POST" action="{{ route('admin.financial-instruments.photos.store', $instrument) }}" enctype="multipart/form-data" class="pt-3">
                @csrf
                <x-form.field label="Resim ekle" for="instrument-photos" name="photos">
                    <input id="instrument-photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple class="panel-file">
                </x-form.field>
                <div class="flex justify-end pt-3">
                    <x-ui.button type="submit" variant="create" icon="fa-solid fa-upload">Yükle</x-ui.button>
                </div>
            </form>
        @endcan
    </x-form.group>
</div>
