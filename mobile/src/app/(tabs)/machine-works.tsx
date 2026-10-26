import { router } from 'expo-router';
import { StyleSheet, View } from 'react-native';

import type { FieldSession } from '@/api/types';
import { Button } from '@/components/button';
import { icons } from '@/components/icons';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { TicketList } from '@/components/ticket-list';
import { SessionBar } from '@/features/field-session/session-bar';
import { SessionGate } from '@/features/field-session/session-gate';
import { useMachineWorkSession } from '@/features/machine-work/queries';
import { spacing } from '@/theme';
import { formatMoney, formatNumber } from '@/utils/number';

export default function MachineWorkListScreen() {
  return <SessionGate>{(session) => <MachineWorkList session={session} />}</SessionGate>;
}

function MachineWorkList({ session }: { session: FieldSession }) {
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
      emptyText="Bu şantiye ve vardiyada bugün kesilmiş makine fişi yok."
      refreshing={works.isRefetching}
      onRefresh={() => void works.refetch()}
      header={
        <View style={styles.header}>
          <SessionBar session={session} />
          <Button title="Yeni makine fişi" icon={icons.add} onPress={() => router.push('/machine-works/new')} />
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
