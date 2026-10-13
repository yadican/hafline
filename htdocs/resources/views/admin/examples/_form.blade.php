@csrf
@php
    $record = $example ?? null;
    $tags = old('tags', $record->tags ?? []);
    $channels = old('channels', $record->channels ?? []);
    $status = old('status', $record->status ?? '');
    $shift = old('shift', $record->shift ?? '');
    $eventTime = old('event_time', $record ? substr((string) $record->event_time, 0, 5) : '');
@endphp
<input type="hidden" name="source" value="{{ old('source', $record->source ?? 'panel') }}">
<table class="panel-form-grid">
    <tr>
        <th><label for="title">Metin</label></th>
        <td><input id="title" name="title" type="text" class="slct" value="{{ old('title', $record->title ?? '') }}" required></td>
    </tr>
    <tr>
        <th><label for="secret">Şifre</label></th>
        <td><input id="secret" name="secret" type="password" class="slct" value="{{ old('secret', $record->secret ?? '') }}" autocomplete="new-password"></td>
    </tr>
    <tr>
        <th><label for="email">E-posta</label></th>
        <td><input id="email" name="email" type="email" class="slct" value="{{ old('email', $record->email ?? '') }}"></td>
    </tr>
    <tr>
        <th><label for="phone">Telefon</label></th>
        <td><input id="phone" name="phone" type="tel" class="slct" value="{{ old('phone', $record->phone ?? '') }}"></td>
    </tr>
    <tr>
        <th><label for="website">Web adresi</label></th>
        <td><input id="website" name="website" type="url" class="slct" value="{{ old('website', $record->website ?? '') }}"></td>
    </tr>
    <tr>
        <th><label for="reference">Arama</label></th>
        <td><input id="reference" name="reference" type="search" class="slct" value="{{ old('reference', $record->reference ?? '') }}"></td>
    </tr>
    <tr>
        <th><label for="quantity">Sayı</label></th>
        <td><input id="quantity" name="quantity" type="number" class="slct" min="0" value="{{ old('quantity', $record->quantity ?? '') }}"></td>
    </tr>
    <tr>
        <th><label for="amount">Tutar</label></th>
        <td><input id="amount" name="amount" type="number" class="slct" min="0" step="0.01" value="{{ old('amount', $record->amount ?? '') }}"></td>
    </tr>
    <tr>
        <th><label for="event_date">Tarih</label></th>
        <td><input id="event_date" name="event_date" type="date" class="slct" value="{{ old('event_date', $record?->event_date?->format('Y-m-d')) }}"></td>
    </tr>
    <tr>
        <th><label for="event_time">Saat</label></th>
        <td><input id="event_time" name="event_time" type="time" class="slct" value="{{ $eventTime }}"></td>
    </tr>
    <tr>
        <th><label for="starts_at">Tarih ve saat</label></th>
        <td><input id="starts_at" name="starts_at" type="datetime-local" class="slct" value="{{ old('starts_at', $record?->starts_at?->format('Y-m-d\TH:i')) }}"></td>
    </tr>
    <tr>
        <th><label for="event_month">Ay</label></th>
        <td><input id="event_month" name="event_month" type="month" class="slct" value="{{ old('event_month', $record->event_month ?? '') }}"></td>
    </tr>
    <tr>
        <th><label for="event_week">Hafta</label></th>
        <td><input id="event_week" name="event_week" type="week" class="slct" value="{{ old('event_week', $record->event_week ?? '') }}"></td>
    </tr>
    <tr>
        <th><label for="color">Renk</label></th>
        <td><input id="color" name="color" type="color" class="slct" value="{{ old('color', $record->color ?? '#c4892e') }}"></td>
    </tr>
    <tr>
        <th><label for="level">Aralık</label></th>
        <td><input id="level" name="level" type="range" min="0" max="100" value="{{ old('level', $record->level ?? 0) }}"></td>
    </tr>
    <tr>
        <th><label for="note">Uzun metin</label></th>
        <td><textarea id="note" name="note" class="slct" rows="3">{{ old('note', $record->note ?? '') }}</textarea></td>
    </tr>
    <tr>
        <th><label for="status">Seçim listesi</label></th>
        <td>
            <select id="status" name="status" class="slct">
                <option value="">Seçin</option>
                <option value="taslak" @selected($status === 'taslak')>Taslak</option>
                <option value="aktif" @selected($status === 'aktif')>Aktif</option>
                <option value="kapali" @selected($status === 'kapali')>Kapalı</option>
            </select>
        </td>
    </tr>
    <tr>
        <th><label for="channels">Çoklu seçim</label></th>
        <td>
            <select id="channels" name="channels[]" class="slct" multiple size="3">
                <option value="panel" @selected(in_array('panel', $channels, true))>Panel</option>
                <option value="saha" @selected(in_array('saha', $channels, true))>Saha</option>
                <option value="rapor" @selected(in_array('rapor', $channels, true))>Rapor</option>
            </select>
        </td>
    </tr>
    <tr>
        <th>Onay kutuları</th>
        <td>
            <label class="panel-choice"><input type="checkbox" name="tags[]" value="nakit" @checked(in_array('nakit', $tags, true))> Nakit</label>
            <label class="panel-choice"><input type="checkbox" name="tags[]" value="sefer" @checked(in_array('sefer', $tags, true))> Sefer</label>
            <label class="panel-choice"><input type="checkbox" name="tags[]" value="santiye" @checked(in_array('santiye', $tags, true))> Şantiye</label>
        </td>
    </tr>
    <tr>
        <th>Tek seçim</th>
        <td>
            <label class="panel-choice"><input type="radio" name="shift" value="gunduz" @checked($shift === 'gunduz')> Gündüz</label>
            <label class="panel-choice"><input type="radio" name="shift" value="gece" @checked($shift === 'gece')> Gece</label>
        </td>
    </tr>
    <tr>
        <th>Aktif</th>
        <td>
            <label class="panel-choice"><input type="checkbox" name="active" value="1" @checked(old('active', $record->active ?? false))> Kayıt aktif</label>
        </td>
    </tr>
    <tr>
        <th><label for="attachment">Dosya</label></th>
        <td>
            <input id="attachment" name="attachment" type="file" class="panel-file">
            @if ($record?->attachment)
                <span class="panel-hint">{{ basename($record->attachment) }}</span>
            @endif
        </td>
    </tr>
    <tr>
        <th>&nbsp;</th>
        <td class="panel-form-actions">
            <button type="submit" class="panel-btn">
                <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> {{ $submitLabel }}
            </button>
            <a href="{{ route('admin.examples.index') }}" class="panel-arama-toggle" style="margin-left:8px;">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Listeye dön
            </a>
        </td>
    </tr>
</table>
