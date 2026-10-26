import { StyleSheet, View } from 'react-native';

import { Button } from '@/components/button';
import { ErrorBanner } from '@/components/screen-state';
import { spacing } from '@/theme';

import type { Receipt } from './receipt';
import { useReceiptPrint, useThermalPrinterAvailable } from './use-receipt-print';

export function PrintActions({ receipt }: { receipt: Receipt }) {
  const { printThermal, print, share, pending, error } = useReceiptPrint(receipt);
  const thermalAvailable = useThermalPrinterAvailable();
  const busy = pending !== null;

  return (
    <View style={styles.actions}>
      {error ? <ErrorBanner message={error} /> : null}
      {thermalAvailable ? (
        <Button title="Yazdır" onPress={() => void printThermal()} loading={pending === 'thermal'} disabled={busy} />
      ) : null}
      <Button
        title={thermalAvailable ? 'Başka yazıcıya yazdır' : 'Yazdır'}
        variant={thermalAvailable ? 'secondary' : 'primary'}
        onPress={() => void print()}
        loading={pending === 'print'}
        disabled={busy}
      />
      <Button
        title="PDF paylaş"
        variant="secondary"
        onPress={() => void share()}
        loading={pending === 'share'}
        disabled={busy}
      />
    </View>
  );
}

const styles = StyleSheet.create({
  actions: {
    gap: spacing.sm,
  },
});
