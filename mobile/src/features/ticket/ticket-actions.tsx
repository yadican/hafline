import { Alert, StyleSheet, View } from 'react-native';

import { errorMessage } from '@/api/errors';
import { ActionTile, ActionTileRow } from '@/components/action-tile';
import { Button } from '@/components/button';
import { icons } from '@/components/icons';
import { ErrorBanner } from '@/components/screen-state';
import type { Receipt } from '@/features/print/receipt';
import { useReceiptPrint, useThermalPrinterAvailable } from '@/features/print/use-receipt-print';
import { spacing } from '@/theme';

type TicketActionsProps = {
  receipt: Receipt;
  newTitle: string;
  onNew: () => void;
  onEdit: () => void;
  onDelete: () => void;
  deleting: boolean;
  deleteError: unknown;
};

export function TicketActions({ receipt, newTitle, onNew, onEdit, onDelete, deleting, deleteError }: TicketActionsProps) {
  const { printThermal, print, share, pending, error } = useReceiptPrint(receipt);
  const thermalAvailable = useThermalPrinterAvailable();
  const busy = pending !== null || deleting;

  const confirmDelete = () => {
    Alert.alert('Fişi sil', 'Bu fiş ve cari kayıtları silinsin mi?', [
      { text: 'Vazgeç', style: 'cancel' },
      { text: 'Sil', style: 'destructive', onPress: onDelete },
    ]);
  };

  return (
    <View style={styles.actions}>
      {error ? <ErrorBanner message={error} /> : null}
      {deleteError ? <ErrorBanner message={errorMessage(deleteError)} /> : null}

      <Button
        title="Yazdır"
        icon={icons.print}
        onPress={() => void (thermalAvailable ? printThermal() : print())}
        loading={pending === (thermalAvailable ? 'thermal' : 'print')}
        disabled={busy}
      />

      <ActionTileRow>
        {thermalAvailable ? (
          <ActionTile
            title="Başka yazıcı"
            icon={icons.otherPrinter}
            onPress={() => void print()}
            loading={pending === 'print'}
            disabled={busy}
          />
        ) : null}
        <ActionTile
          title="PDF paylaş"
          icon={icons.share}
          onPress={() => void share()}
          loading={pending === 'share'}
          disabled={busy}
        />
        <ActionTile title="Düzenle" icon={icons.edit} onPress={onEdit} disabled={busy} />
      </ActionTileRow>

      <Button title={newTitle} variant="secondary" icon={icons.add} onPress={onNew} disabled={deleting} />

      <Button title="Fişi sil" variant="dangerText" icon={icons.delete} onPress={confirmDelete} loading={deleting} />
    </View>
  );
}

const styles = StyleSheet.create({
  actions: {
    gap: spacing.sm,
  },
});
