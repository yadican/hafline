import { router } from 'expo-router';
import { useState } from 'react';
import { ScrollView, StyleSheet, Text } from 'react-native';

import { ApiError, errorMessage, formErrorMessage } from '@/api/errors';
import type { MachinePlateScan, MachineWorkTicket } from '@/api/types';
import { Button } from '@/components/button';
import { FormSection } from '@/components/form-section';
import { PlateInput } from '@/components/plate-input';
import { PlatePhoto } from '@/components/plate-photo';
import { ErrorBanner, LoadingState } from '@/components/screen-state';
import { SelectField } from '@/components/select-field';
import { TextField } from '@/components/text-field';
import { MIN_PLATE_QUERY, scanReadLine } from '@/features/plate/plate';
import { PlateCheckResult } from '@/features/plate/plate-check-result';
import { colors, fontSize, fonts, spacing } from '@/theme';
import { decimalForInput, decimalInput, formatMoney, parseDecimal } from '@/utils/number';
import { useDebouncedValue } from '@/utils/use-debounced-value';

import {
  useCreateMachineWork,
  useMachinePlateCheck,
  useMachineSearch,
  useMachineWorkOptions,
  useScanMachinePlate,
  useUpdateMachineWork,
} from './queries';

const NO_OPERATOR = '';

const SHOWN_FIELDS = ['plate', 'hours', 'hourly_rate', 'operator_id', 'operator_rate'];

type MachineWorkFormProps =
  | { mode: 'create'; fieldSessionId: string }
  | { mode: 'edit'; ticket: MachineWorkTicket };

export function MachineWorkForm(props: MachineWorkFormProps) {
  const editing = props.mode === 'edit' ? props.ticket : null;

  const options = useMachineWorkOptions();
  const scanPlate = useScanMachinePlate();
  const createWork = useCreateMachineWork();
  const updateWork = useUpdateMachineWork(editing?.id ?? '');
  const save = editing ? updateWork : createWork;

  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [scan, setScan] = useState<MachinePlateScan | null>(null);
  const [plate, setPlate] = useState(editing?.plate ?? '');
  const [hours, setHours] = useState(editing?.hours_label ?? '');
  const [hourlyRate, setHourlyRate] = useState(decimalForInput(editing?.hourly_rate));
  const [operatorId, setOperatorId] = useState(editing?.operator?.id ?? NO_OPERATOR);
  const [operatorRate, setOperatorRate] = useState(decimalForInput(editing?.operator_rate));

  const typedPlate = useDebouncedValue(plate.trim());
  const search = useMachineSearch(typedPlate);
  const check = useMachinePlateCheck(typedPlate);

  const operators = options.data?.operators ?? [];
  const operator = operators.find((item) => item.id === operatorId);
  const needsOperatorRate = operator !== undefined && !operator.salaried;
  const rateRequired = check.data?.rate_required === true;

  const startScan = (uri: string) => {
    if (props.mode !== 'create') {
      return;
    }

    setPhotoUri(uri);
    setScan(null);
    createWork.reset();
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

  const selectOperator = (value: string) => {
    const selected = operators.find((item) => item.id === value);

    setOperatorId(value);
    setOperatorRate(selected && !selected.salaried ? decimalForInput(selected.last_rate) : '');
  };

  const submit = () => {
    const body = {
      plate: plate.trim(),
      hours: parseDecimal(hours) ?? 0,
      hourly_rate: parseDecimal(hourlyRate),
      operator_id: operatorId === NO_OPERATOR ? null : operatorId,
      operator_rate: needsOperatorRate ? parseDecimal(operatorRate) : null,
    };

    if (editing) {
      updateWork.mutate(body, { onSuccess: () => router.back() });

      return;
    }

    if (props.mode !== 'create') {
      return;
    }

    createWork.mutate(
      { ...body, field_session_id: props.fieldSessionId, scan_id: scan?.scan_id ?? null },
      {
        onSuccess: (ticket) =>
          router.replace({ pathname: '/machine-works/[id]', params: { id: ticket.id, saved: '1' } }),
      },
    );
  };

  const fieldError = (field: string) => (save.error instanceof ApiError ? save.error.firstFieldError(field) : undefined);
  const operatorError = fieldError('operator_id');
  const saveError = formErrorMessage(save.error, SHOWN_FIELDS);

  const canSave =
    plate.trim() !== '' &&
    parseDecimal(hours) !== null &&
    check.data?.can_create !== false &&
    (!rateRequired || parseDecimal(hourlyRate) !== null) &&
    (!needsOperatorRate || parseDecimal(operatorRate) !== null);

  const defaultRate = check.data?.hourly_rate;

  return (
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      {props.mode === 'create' ? (
        <PlatePhoto
          photoUri={photoUri}
          hint="İsterseniz makine plakasının fotoğrafını çekin; plakayı aşağıya elle de yazabilirsiniz."
          scanning={scanPlate.isPending}
          scanError={scanPlate.error}
          onPhoto={startScan}
          onRetry={() => photoUri && startScan(photoUri)}
          busy={createWork.isPending}
        />
      ) : null}

      {scan ? <Text style={styles.hint}>{scanReadLine(scan)}</Text> : null}

      <PlateInput
        value={plate}
        onChangeText={setPlate}
        error={fieldError('plate')}
        suggestions={(search.data ?? []).map((machine) => ({
          plate: machine.plate,
          detail: [machine.class_name, machine.owner_name].filter(Boolean).join('  ·  ') || null,
        }))}
      />
      <PlateCheckResult
        active={typedPlate.length >= MIN_PLATE_QUERY}
        loading={check.isFetching}
        error={check.error}
        check={check.data}
        noun="makine"
        lines={defaultRate ? [`Saat ücreti: ${formatMoney(defaultRate)}`] : []}
        warning={rateRequired ? check.data?.message : null}
      />

      <TextField
        label="Çalışılan saat"
        value={hours}
        onChangeText={(text) => setHours(decimalInput(text))}
        placeholder="4,5"
        keyboardType="decimal-pad"
        maxLength={5}
        error={fieldError('hours')}
      />
      <TextField
        label={rateRequired ? 'Saat ücreti (zorunlu)' : 'Saat ücreti'}
        value={hourlyRate}
        onChangeText={(text) => setHourlyRate(decimalInput(text))}
        placeholder={defaultRate ? `Boş bırakılırsa ${formatMoney(defaultRate)}` : 'Saat ücreti'}
        keyboardType="decimal-pad"
        maxLength={13}
        error={fieldError('hourly_rate')}
      />

      <FormSection title="Operatör">
        {options.isPending ? (
          <LoadingState />
        ) : options.isError ? (
          <ErrorBanner message={errorMessage(options.error)} />
        ) : (
          <SelectField
            title="Operatör seçin"
            placeholder="Operatör seçin"
            options={[
              { value: NO_OPERATOR, label: 'Operatör yok' },
              ...operators.map((item) => ({ value: item.id, label: item.salaried ? `${item.name} (personel)` : item.name })),
            ]}
            selected={operatorId}
            onSelect={selectOperator}
            emptyText="Tanımlı operatör yok."
          />
        )}
        {operatorError ? <ErrorBanner message={operatorError} /> : null}
      </FormSection>

      {needsOperatorRate ? (
        <TextField
          label="Operatör saat ücreti"
          value={operatorRate}
          onChangeText={(text) => setOperatorRate(decimalInput(text))}
          placeholder="350"
          keyboardType="decimal-pad"
          maxLength={13}
          error={fieldError('operator_rate')}
        />
      ) : null}

      {saveError ? <ErrorBanner message={saveError} /> : null}
      <Button
        title={editing ? 'Değişiklikleri kaydet' : 'Fişi kaydet'}
        variant={editing ? 'edit' : 'create'}
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
