import { router } from 'expo-router';
import { useState } from 'react';
import { ScrollView, StyleSheet, Text } from 'react-native';

import { ApiError, formErrorMessage } from '@/api/errors';
import type { HaulTicket, PlateScan } from '@/api/types';
import { Button } from '@/components/button';
import { PlateInput } from '@/components/plate-input';
import { PlatePhoto } from '@/components/plate-photo';
import { ErrorBanner } from '@/components/screen-state';
import { TextField } from '@/components/text-field';
import { MIN_PLATE_QUERY, scanReadLine } from '@/features/plate/plate';
import { PlateCheckResult } from '@/features/plate/plate-check-result';
import { integerInput } from '@/utils/number';
import { useDebouncedValue } from '@/utils/use-debounced-value';
import { colors, fontSize, fonts, spacing } from '@/theme';

import { useCreateHaul, useHaulPlateCheck, useHaulVehicleSearch, useScanHaulPlate, useUpdateHaul } from './queries';

type HaulFormProps = { mode: 'create'; fieldSessionId: string } | { mode: 'edit'; ticket: HaulTicket };

export function HaulForm(props: HaulFormProps) {
  const editing = props.mode === 'edit' ? props.ticket : null;

  const scanPlate = useScanHaulPlate();
  const createHaul = useCreateHaul();
  const updateHaul = useUpdateHaul(editing?.id ?? '');
  const save = editing ? updateHaul : createHaul;

  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [scan, setScan] = useState<PlateScan | null>(null);
  const [plate, setPlate] = useState(editing?.plate ?? '');
  const [tonnage, setTonnage] = useState(editing ? String(editing.tonnage) : '');

  const typedPlate = useDebouncedValue(plate.trim());
  const search = useHaulVehicleSearch(typedPlate);
  const check = useHaulPlateCheck(typedPlate);

  const startScan = (uri: string) => {
    if (props.mode !== 'create') {
      return;
    }

    setPhotoUri(uri);
    setScan(null);
    setPlate('');
    setTonnage('');
    createHaul.reset();
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

  const submit = () => {
    const body = { plate: plate.trim(), tonnage: Number(tonnage) };

    if (editing) {
      updateHaul.mutate(body, { onSuccess: () => router.back() });

      return;
    }

    if (props.mode !== 'create' || !scan) {
      return;
    }

    createHaul.mutate(
      { ...body, scan_id: scan.scan_id, field_session_id: props.fieldSessionId },
      {
        onSuccess: (ticket) => router.replace({ pathname: '/hauls/[id]', params: { id: ticket.id, saved: '1' } }),
      },
    );
  };

  const fieldError = (field: string) => (save.error instanceof ApiError ? save.error.firstFieldError(field) : undefined);
  const plateError = fieldError('plate');
  const tonnageError = fieldError('tonnage');
  const saveError = formErrorMessage(save.error, ['plate', 'tonnage']);
  const showFields = editing !== null || scan !== null;

  return (
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      {props.mode === 'create' ? (
        <PlatePhoto
          photoUri={photoUri}
          hint="Plaka okunaklı ve kadrajın ortasında olacak şekilde fotoğraf çekin ya da galeriden seçin."
          scanning={scanPlate.isPending}
          scanError={scanPlate.error}
          onPhoto={startScan}
          onRetry={() => photoUri && startScan(photoUri)}
          busy={createHaul.isPending}
        />
      ) : null}

      {scan ? <Text style={styles.hint}>{scanReadLine(scan)}</Text> : null}

      {showFields ? (
        <>
          <PlateInput
            value={plate}
            onChangeText={setPlate}
            error={plateError}
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
          <TextField
            label="Tonaj"
            value={tonnage}
            onChangeText={(text) => setTonnage(integerInput(text))}
            placeholder="22"
            keyboardType="number-pad"
            maxLength={4}
            error={tonnageError}
          />
          {saveError ? <ErrorBanner message={saveError} /> : null}
          <Button
            title={editing ? 'Değişiklikleri kaydet' : 'Fişi kaydet'}
            variant={editing ? 'edit' : 'create'}
            onPress={submit}
            loading={save.isPending}
            disabled={plate.trim() === '' || tonnage === '' || check.data?.can_create === false}
          />
        </>
      ) : null}
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
