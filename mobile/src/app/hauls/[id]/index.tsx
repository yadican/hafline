import { router, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet } from 'react-native';

import { Button } from '@/components/button';
import { InfoRow } from '@/components/info-row';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { SavedBanner, TicketActions, TicketCard } from '@/components/ticket-card';
import { useDeleteHaul, useHaulTicket } from '@/features/haul/queries';
import { spacing } from '@/theme';

export default function HaulTicketScreen() {
  const { id, saved } = useLocalSearchParams<{ id: string; saved?: string }>();
  const ticket = useHaulTicket(id);
  const deleteHaul = useDeleteHaul(id);

  if (ticket.isPending) {
    return <LoadingState />;
  }

  if (ticket.isError) {
    return <ErrorState error={ticket.error} onRetry={() => void ticket.refetch()} />;
  }

  return (
    <ScrollView contentContainerStyle={styles.content}>
      {saved ? <SavedBanner /> : null}

      <TicketCard title={ticket.data.title} ticketNo={ticket.data.ticket_no}>
        <InfoRow label="Tarih" value={ticket.data.hauled_at_label} />
        <InfoRow label="Plaka" value={ticket.data.plate ?? '-'} />
        <InfoRow label="Şantiye" value={ticket.data.site_name ?? '-'} />
        <InfoRow label="Döküm yeri" value={ticket.data.dump_site_name ?? '-'} />
        <InfoRow label="Vardiya" value={ticket.data.shift_label} />
        <InfoRow label="Tonaj" value={String(ticket.data.tonnage)} />
      </TicketCard>

      <Button title="Yeni taşıma fişi" variant="create" onPress={() => router.replace('/hauls/new')} />
      <TicketActions
        onEdit={() => router.push({ pathname: '/hauls/[id]/edit', params: { id } })}
        onDelete={() => deleteHaul.mutate(undefined, { onSuccess: () => router.back() })}
        deleting={deleteHaul.isPending}
        deleteError={deleteHaul.error}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  content: {
    gap: spacing.md,
    padding: spacing.lg,
  },
});
