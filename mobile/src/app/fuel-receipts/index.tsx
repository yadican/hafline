import { router } from 'expo-router';

import { Button } from '@/components/button';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { TicketList } from '@/components/ticket-list';
import { RequireSession } from '@/features/field-session/require-session';
import { useFuelReceiptSession } from '@/features/fuel-receipt/queries';
import { formatMoney, formatNumber } from '@/utils/number';

export default function FuelReceiptListScreen() {
  return <RequireSession>{() => <FuelReceiptList />}</RequireSession>;
}

function FuelReceiptList() {
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
      emptyText="Bu oturumda bugün kesilmiş yakıt fişi yok."
      refreshing={receipts.isRefetching}
      onRefresh={() => void receipts.refetch()}
      header={<Button title="Yeni yakıt fişi" variant="create" onPress={() => router.push('/fuel-receipts/new')} />}
    />
  );
}
