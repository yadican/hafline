{{-- $type: PartyType, $lockedRoles: list<PartyRole> (kayıtlar bağlı olduğu için kaldırılamayan roller) --}}
@php
    $isPerson = $type === \App\Enums\PartyType::Person;
    $isSystem = isset($party) && $party->isSystem();
    $locked = collect($lockedRoles ?? [])->map->value->all();
    $selectedRoles = collect(old('roles', data_get($values ?? [], 'roles', [])))
        ->map(fn ($role) => $role instanceof \BackedEnum ? $role->value : $role)
        ->merge($locked)
        ->all();
@endphp

@if ($isPerson)
    <x-form.group legend="Kişi" icon="fa-solid fa-user" description="Ad, soyad ve kimlik bilgisi">
        <x-form.input name="first_name" label="Ad" maxlength="80" required autofocus />
        <x-form.input name="last_name" label="Soyad" maxlength="80" required />
        <x-form.input name="national_id" label="T.C. kimlik no" maxlength="11" inputmode="numeric" />
        <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde görünsün" :checked="true" />
        <x-form.textarea name="notes" label="Not" rows="2" maxlength="2000" />
    </x-form.group>
@else
    <x-form.group legend="Şirket" icon="fa-solid fa-building" description="Unvan ve vergi bilgisi">
        @if ($isSystem)
            <x-form.input name="title" label="Unvan" maxlength="200" required readonly />
        @else
            <x-form.input name="title" label="Unvan" maxlength="200" required autofocus />
        @endif
        <x-form.input name="tax_office" label="Vergi dairesi" maxlength="100" />
        <x-form.input name="tax_number" label="Vergi no" maxlength="11" inputmode="numeric" />
        @if ($isSystem)
            <input type="hidden" name="active" value="1">
            <p class="panel-hint">Sistem carisidir: sahada kayıtlı olmayan plakalar buraya bağlanır. Adı değiştirilemez, pasife alınamaz ve silinemez.</p>
        @else
            <x-form.checkbox name="active" label="Durum" text="Seçim listelerinde görünsün" :checked="true" />
        @endif
        <x-form.textarea name="notes" label="Not" rows="2" maxlength="2000" />
    </x-form.group>
@endif

<x-form.group legend="Roller ve durum" icon="fa-solid fa-tags" description="Bu kartın işletmedeki rolleri">
    <x-form.field label="Roller" name="roles">
        <div class="panel-choices">
            @foreach (\App\Enums\PartyRole::forType($type) as $role)
                @php
                    $isLocked = in_array($role->value, $locked, true);
                    $needsConfirm = $role->requiresAssignmentConfirm() && ! $isLocked;
                @endphp
                <label class="panel-choice" @if ($isLocked) title="{{ $role->lockedHint() }}" @endif>
                    <input
                        type="checkbox"
                        name="roles[]"
                        value="{{ $role->value }}"
                        @checked(in_array($role->value, $selectedRoles, true))
                        @disabled($isLocked)
                        @if ($needsConfirm)
                            data-role-confirm
                            data-role-confirm-title="{{ $role->assignmentConfirmTitle() }}"
                            data-role-confirm-text="{{ $role->assignmentConfirmText() }}"
                            data-role-confirm-button="Evet, aç"
                            data-role-confirm-icon="fa-solid fa-triangle-exclamation"
                        @endif
                    >
                    {{ $role->label() }}
                    @if ($isLocked)
                        <span class="panel-choice-lock" aria-hidden="true"><i class="fa-solid fa-lock"></i></span>
                    @endif
                </label>
            @endforeach
        </div>
    </x-form.field>
    <p class="panel-hint">
        Roller Cari’den verilir; araç, döküm, istasyon veya şantiye bağında kendiliğinden de eklenir.
        @if ($isPerson)
            <strong>Personel</strong> gibi sert roller yalnızca kişi kartlarında açılır; işaretlenince ilgili kart oluşur ve Cari’den kaldırılamaz — kaldırma yalnızca o modülün ekranından yapılır.
        @endif
    </p>
</x-form.group>

<x-form.group legend="İletişim Mobil" icon="fa-solid fa-phone" description="Telefon, e-posta">
    <x-form.input name="mobile" label="Cep telefonu" type="tel" maxlength="20" />
    <x-form.input name="phone" label="Telefon" type="tel" maxlength="20" />
    @unless ($isPerson)
        <x-form.input name="fax" label="Faks" type="tel" maxlength="20" />
    @endunless
    <x-form.input name="email" label="E-posta" type="email" maxlength="120" />
</x-form.group>
<x-form.group legend="İletişim Adres" icon="fa-solid fa-address-book" description="İl / ilçe / semt ve adres">
    <x-form.location :cities="$locations['cities']" :districts="$locations['districts']" :neighborhoods="$locations['neighborhoods']" />
    <x-form.textarea name="address" label="Adres" rows="2" maxlength="500" placeholder="Cadde, sokak, bina ve kapı no" />
</x-form.group>
