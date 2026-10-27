import { StyleSheet, Text } from 'react-native';

import { errorMessage } from '@/api/errors';
import { PlateStatus } from '@/components/plate-status';
import { ErrorBanner } from '@/components/screen-state';
import { colors, fontSize, fonts } from '@/theme';

type PlateCheck = {
  vehicle: { plate: string | null };
  owner_name: string | null;
  can_create: boolean;
  message: string | null;
};

type PlateCheckResultProps = {
  active: boolean;
  loading: boolean;
  error: unknown;
  check: PlateCheck | undefined;
  /** "makine" or "araç", used in the "Kayıtlı …" title. */
  noun: string;
  lines?: string[];
  /** Shown in warning colours even when the ticket can be created. */
  warning?: string | null;
};

export function PlateCheckResult({ active, loading, error, check, noun, lines = [], warning }: PlateCheckResultProps) {
  if (!active) {
    return null;
  }

  if (error) {
    return <ErrorBanner message={errorMessage(error)} />;
  }

  if (!check) {
    return loading ? <Text style={styles.checking}>Plaka kontrol ediliyor…</Text> : null;
  }

  return (
    <PlateStatus
      ready={check.can_create && !warning}
      title={check.can_create ? `Kayıtlı ${noun}: ${check.vehicle.plate}` : (check.message ?? 'Plakayı kontrol edin.')}
      lines={[
        ...(check.owner_name ? [`Sahibi: ${check.owner_name}`] : []),
        ...lines,
        ...(check.can_create && warning ? [warning] : []),
      ]}
    />
  );
}

const styles = StyleSheet.create({
  checking: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
});
