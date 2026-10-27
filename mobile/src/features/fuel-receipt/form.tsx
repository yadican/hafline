import { router } from 'expo-router';
import { useState } from 'react';
import { ScrollView, StyleSheet, Text } from 'react-native';

import { ApiError, errorMessage, formErrorMessage } from '@/api/errors';
import type { FuelPlateScan, FuelReceiptTicket, FuelStationOption } from '@/api/types';
import { Button } from '@/components/button';
import { icons } from '@/components/icons';
import { FormSection } from '@/components/form-section';
import { PlateInput } from '@/components/plate-input';
import { PlatePhoto } from '@/components/plate-photo';
import { ErrorBanner, LoadingState } from '@/components/screen-state';
import { SelectField } from '@/components/select-field';
import { TextField } from '@/components/text-field';
import { MIN_PLATE_QUERY, scanReadLine } from '@/features/plate/plate';
import { PlateCheckResult } from '@/features/plate/plate-check-result';
import { colors, fontSize, fonts, spacing } from '@/theme';
import { decimalForInput, decimalInput, integerInput, parseDecimal, parseInteger } from '@/utils/number';
import { useDebouncedValue } from '@/utils/use-debounced-value';

import {
  useCreateFuelReceipt,
  useFuelPlateCheck,
  useFuelReceiptOptions,
  useFuelVehicleSearch,
  useScanFuelPlate,
  useUpdateFuelReceipt,
} from './queries';

const SHOWN_FIELDS = ['plate', 'fuel_station_id', 'quantity', 'unit_price', 'km', 'received_by', 'haul_ticket_count'];

const UNIT_PRICE_DECIMALS = 4;

type FuelReceiptFormProps =
  | { mode: 'create'; fieldSessionId: string }
  | { mode: 'edit'; ticket: FuelReceiptTicket };

function hasPrice(station: FuelStationOption | undefined): boolean {
  return station !== undefined && Number(station.unit_price) > 0;
}

function stationLabel(station: FuelStationOption): string {
  const price = hasPrice(station) ? `${decimalForInput(station.unit_price)} ₺/L` : 'fiyat yok';

  return `${station.name}  ·  ${price}`;
}

export function FuelReceiptForm(props: FuelReceiptFormProps) {
  const editing = props.mode === 'edit' ? props.ticket : null;

  const options = useFuelReceiptOptions();
  const scanPlate = useScanFuelPlate();
  const createReceipt = useCreateFuelReceipt();
  const updateReceipt = useUpdateFuelReceipt(editing?.id ?? '');
  const save = editing ? updateReceipt : createReceipt;

  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [scan, setScan] = useState<FuelPlateScan | null>(null);
  const [plate, setPlate] = useState(editing?.plate ?? '');
  const [stationId, setStationId] = useState<string | null>(editing?.station?.id ?? null);
  const [quantity, setQuantity] = useState(editing?.quantity_label ?? '');
  const [unitPrice, setUnitPrice] = useState(decimalForInput(editing?.unit_price));
  const [km, setKm] = useState(editing?.km != null ? String(editing.km) : '');
  const [receivedBy, setReceivedBy] = useState(editing?.received_by ?? '');
  const [haulTicketCount, setHaulTicketCount] = useState(
    editing?.haul_ticket_count != null ? String(editing.haul_ticket_count) : '',
  );

  const typedPlate = useDebouncedValue(plate.trim());
  const search = useFuelVehicleSearch(typedPlate);
  const check = useFuelPlateCheck(typedPlate);

  const stations = options.data?.stations ?? [];
  const station = stations.find((item) => item.id === stationId);
  const priceRequired = station !== undefined && !hasPrice(station);

  const startScan = (uri: string) => {
    if (props.mode !== 'create') {
      return;
    }

    setPhotoUri(uri);
    setScan(null);
    createReceipt.reset();
    scanPlate.mutate(
      { fieldSessionId: props.fieldSessionId, photoUri: uri },
      {
        onSuccess: (result) => {
          setScan(result);
          setPlate(result.vehicle.plate ?? result.plate ?? '');
        },
      },
    );
  };

  const selectStation = (value: string) => {
    const selected = stations.find((item) => item.id === value);

    setStationId(value);
    setUnitPrice(hasPrice(selected) ? decimalForInput(selected?.unit_price) : '');
  };

  const submit = () => {
    if (!stationId) {
      return;
    }

    const body = {
      plate: plate.trim(),
      fuel_station_id: stationId,
      quantity: parseDecimal(quantity) ?? 0,
      unit_price: parseDecimal(unitPrice),
      km: parseInteger(km),
      received_by: receivedBy.trim() || null,
      haul_ticket_count: parseInteger(haulTicketCount),
    };

    if (editing) {
      updateReceipt.mutate(body, { onSuccess: () => router.back() });

      return;
    }

    if (props.mode !== 'create') {
      return;
    }

    createReceipt.mutate(
      { ...body, field_session_id: props.fieldSessionId, scan_id: scan?.scan_id ?? null },
      {
        onSuccess: (ticket) =>
          router.replace({ pathname: '/fuel-receipts/[id]', params: { id: ticket.id, saved: '1' } }),
      },
    );
  };

  const fieldError = (field: string) => (save.error instanceof ApiError ? save.error.firstFieldError(field) : undefined);
  const stationError = fieldError('fuel_station_id');
  const saveError = formErrorMessage(save.error, SHOWN_FIELDS);

  const canSave =
    plate.trim() !== '' &&
    stationId !== null &&
    parseDecimal(quantity) !== null &&
    check.data?.can_create !== false &&
    (!priceRequired || parseDecimal(unitPrice) !== null);

  return (
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      {props.mode === 'create' ? (
        <PlatePhoto
          photoUri={photoUri}
          hint="İsterseniz araç plakasının fotoğrafını çekin; plakayı aşağıya elle de yazabilirsiniz."
          scanning={scanPlate.isPending}
          scanError={scanPlate.error}
          onPhoto={startScan}
          onRetry={() => photoUri && startScan(photoUri)}
          busy={createReceipt.isPending}
        />
      ) : null}

      {scan ? <Text style={styles.hint}>{scanReadLine(scan)}</Text> : null}

      <PlateInput
        value={plate}
        onChangeText={setPlate}
        error={fieldError('plate')}
        suggestions={(search.data ?? []).map((vehicle) => ({
          plate: vehicle.plate,
          detail: [vehicle.class_name, vehicle.owner_name].filter(Boolean).join('  ·  ') || null,
        }))}
      />
      <PlateCheckResult
        active={typedPlate.length >= MIN_PLATE_QUERY}
        loading={check.isFetching}
        error={check.error}
        check={check.data}
        noun="araç"
      />

      <FormSection title="Yakıt yeri">
        {options.isPending ? (
          <LoadingState />
        ) : options.isError ? (
          <ErrorBanner message={errorMessage(options.error)} />
        ) : (
          <SelectField
            title="Yakıt yeri seçin"
            placeholder="Yakıt yeri seçin"
            options={stations.map((item) => ({ value: item.id, label: stationLabel(item) }))}
            selected={stationId}
            onSelect={selectStation}
            emptyText="Bu şantiyeye bağlı aktif yakıt yeri yok."
          />
        )}
        {stationError ? <ErrorBanner message={stationError} /> : null}
      </FormSection>

      <TextField
        label="Litre"
        value={quantity}
        onChangeText={(text) => setQuantity(decimalInput(text))}
        placeholder="Litreyi girin"
        keyboardType="decimal-pad"
        maxLength={8}
        error={fieldError('quantity')}
      />
      <TextField
        label={priceRequired ? 'Birim fiyat (zorunlu)' : 'Birim fiyat'}
        value={unitPrice}
        onChangeText={(text) => setUnitPrice(decimalInput(text, UNIT_PRICE_DECIMALS))}
        placeholder={station && hasPrice(station) ? `Boş bırakılırsa ${decimalForInput(station.unit_price)}` : 'Birim fiyatı girin'}
        keyboardType="decimal-pad"
        maxLength={9}
        error={fieldError('unit_price')}
      />
      <TextField
        label="Kilometre"
        value={km}
        onChangeText={(text) => setKm(integerInput(text))}
        placeholder="Kilometreyi girin"
        keyboardType="number-pad"
        maxLength={7}
        error={fieldError('km')}
      />
      <TextField
        label="Yakıtı alan kişi"
        value={receivedBy}
        onChangeText={setReceivedBy}
        placeholder="Ad soyad girin"
        autoCapitalize="words"
        maxLength={80}
        error={fieldError('received_by')}
      />
      <TextField
        label="Taşıma fiş adedi"
        value={haulTicketCount}
        onChangeText={(text) => setHaulTicketCount(integerInput(text))}
        placeholder="Fiş adedini girin"
        keyboardType="number-pad"
        maxLength={3}
        error={fieldError('haul_ticket_count')}
      />

      {saveError ? <ErrorBanner message={saveError} /> : null}
      <Button
        title={editing ? 'Değişiklikleri kaydet' : 'Fişi kaydet'}
        icon={icons.save}
        onPress={submit}
        loading={save.isPending}
        disabled={!canSave || scanPlate.isPending}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  content: {
    gap: spacing.md,
    padding: spacing.lg,
  },
  hint: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
});
