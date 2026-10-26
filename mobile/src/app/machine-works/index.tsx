import { router } from 'expo-router';

import { Button } from '@/components/button';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { TicketList } from '@/components/ticket-list';
import { RequireSession } from '@/features/field-session/require-session';
import { useMachineWorkSession } from '@/features/machine-work/queries';
import { formatMoney, formatNumber } from '@/utils/number';

export default function MachineWorkListScreen() {
  return <RequireSession>{() => <MachineWorkList />}</RequireSession>;
}

function MachineWorkList() {
  const works = useMachineWorkSession();

  if (works.isPending) {
    return <LoadingState />;
  }

  if (works.isError) {
    return <ErrorState error={works.error} onRetry={() => void works.refetch()} />;
  }

  return (
    <TicketList
      items={works.data.data.map((work) => ({
        id: work.id,
        title: work.plate ?? '-',
        subtitle: `${work.ticket_no}  ·  ${work.operator_name ?? 'Operatörsüz'}`,
        value: `${formatNumber(work.hours)} saat`,
        valueDetail: formatMoney(work.amount),
      }))}
      onPress={(id) => router.push({ pathname: '/machine-works/[id]', params: { id } })}
      emptyText="Bu oturumda bugün kesilmiş makine fişi yok."
      refreshing={works.isRefetching}
      onRefresh={() => void works.refetch()}
      header={<Button title="Yeni makine fişi" variant="create" onPress={() => router.push('/machine-works/new')} />}
    />
  );
}
