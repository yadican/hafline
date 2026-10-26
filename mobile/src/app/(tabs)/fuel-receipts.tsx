import { router } from 'expo-router';
import { StyleSheet, View } from 'react-native';

import type { FieldSession } from '@/api/types';
import { Button } from '@/components/button';
import { icons } from '@/components/icons';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { TicketList } from '@/components/ticket-list';
import { SessionBar } from '@/features/field-session/session-bar';
import { SessionGate } from '@/features/field-session/session-gate';
import { useFuelReceiptSession } from '@/features/fuel-receipt/queries';
import { spacing } from '@/theme';
import { formatMoney, formatNumber } from '@/utils/number';

export default function FuelReceiptListScreen() {
  return <SessionGate>{(session) => <FuelReceiptList session={session} />}</SessionGate>;
}

function FuelReceiptList({ session }: { session: FieldSession }) {
  const receipts = useFuelReceiptSession();

  if (receipts.isPending) {
    return <LoadingState />;
  }

  if (receipts.isError) {
    return <ErrorState error={receipts.error} onRetry={() => void receipts.refetch()} />;
  }

  return (
    <TicketList
      items={receipts.data.data.map((receipt) => ({
        id: receipt.id,
        title: receipt.plate ?? '-',
        subtitle: `${receipt.ticket_no}  ·  ${receipt.station_name ?? '-'}`,
        value: `${formatNumber(receipt.quantity)} L`,
        valueDetail: formatMoney(receipt.amount),
      }))}
      onPress={(id) => router.push({ pathname: '/fuel-receipts/[id]', params: { id } })}
      emptyText="Bu şantiye ve vardiyada bugün kesilmiş yakıt fişi yok."
      refreshing={receipts.isRefetching}
      onRefresh={() => void receipts.refetch()}
      header={
        <View style={styles.header}>
          <SessionBar session={session} />
          <Button title="Yeni yakıt fişi" icon={icons.add} onPress={() => router.push('/fuel-receipts/new')} />
        </View>
      }
    />
  );
}

const styles = StyleSheet.create({
  header: {
    gap: spacing.sm,
    marginBottom: spacing.sm,
  },
});
