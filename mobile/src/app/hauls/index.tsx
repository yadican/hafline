import { router } from 'expo-router';

import { Button } from '@/components/button';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { TicketList } from '@/components/ticket-list';
import { RequireSession } from '@/features/field-session/require-session';
import { useHaulSession } from '@/features/haul/queries';

export default function HaulListScreen() {
  return <RequireSession>{() => <HaulList />}</RequireSession>;
}

function HaulList() {
  const hauls = useHaulSession();

  if (hauls.isPending) {
    return <LoadingState />;
  }

  if (hauls.isError) {
    return <ErrorState error={hauls.error} onRetry={() => void hauls.refetch()} />;
  }

  return (
    <TicketList
      items={hauls.data.data.map((haul) => ({
        id: haul.id,
        title: haul.plate ?? '-',
        subtitle: `${haul.ticket_no}  ·  ${haul.hauled_at_label}`,
      }))}
      onPress={(id) => router.push({ pathname: '/hauls/[id]', params: { id } })}
      emptyText="Bu oturumda bugün kesilmiş taşıma fişi yok."
      refreshing={hauls.isRefetching}
      onRefresh={() => void hauls.refetch()}
      header={<Button title="Yeni taşıma fişi" variant="create" onPress={() => router.push('/hauls/new')} />}
    />
  );
}
