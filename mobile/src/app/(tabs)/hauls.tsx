import { router } from 'expo-router';
import { StyleSheet, View } from 'react-native';

import type { FieldSession } from '@/api/types';
import { Button } from '@/components/button';
import { icons } from '@/components/icons';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { TicketList } from '@/components/ticket-list';
import { SessionBar } from '@/features/field-session/session-bar';
import { SessionGate } from '@/features/field-session/session-gate';
import { useHaulSession } from '@/features/haul/queries';
import { spacing } from '@/theme';

export default function HaulListScreen() {
  return <SessionGate requireDumpSite>{(session) => <HaulList session={session} />}</SessionGate>;
}

function HaulList({ session }: { session: FieldSession }) {
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
      emptyText="Bu şantiye, döküm yeri ve vardiyada bugün kesilmiş taşıma fişi yok."
      refreshing={hauls.isRefetching}
      onRefresh={() => void hauls.refetch()}
      header={
        <View style={styles.header}>
          <SessionBar session={session} showDumpSite />
          <Button title="Yeni taşıma fişi" icon={icons.add} onPress={() => router.push('/hauls/new')} />
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
