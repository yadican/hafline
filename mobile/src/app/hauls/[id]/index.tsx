import { router, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet } from 'react-native';

import { InfoRow } from '@/components/info-row';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { SavedBanner, TicketCard } from '@/components/ticket-card';
import { useDeleteHaul, useHaulTicket } from '@/features/haul/queries';
import type { ReceiptLine } from '@/features/print/receipt';
import { TicketActions } from '@/features/ticket/ticket-actions';
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

  const haul = ticket.data;
  const lines: ReceiptLine[] = [
    { label: 'Tarih', value: haul.hauled_at_label },
    { label: 'Plaka', value: haul.plate ?? '-' },
    { label: 'Şantiye', value: haul.site_name ?? '-' },
    { label: 'Döküm yeri', value: haul.dump_site_name ?? '-' },
    { label: 'Vardiya', value: haul.shift_label },
    { label: 'Tonaj', value: String(haul.tonnage) },
  ];

  return (
    <ScrollView contentContainerStyle={styles.content}>
      {saved ? <SavedBanner /> : null}

      <TicketCard title={haul.title} ticketNo={haul.ticket_no}>
        {lines.map((line) => (
          <InfoRow key={line.label} label={line.label} value={line.value} />
        ))}
      </TicketCard>

      <TicketActions
        receipt={{ title: haul.title, ticketNo: haul.ticket_no, lines, qrPayload: haul.qr_payload }}
        newTitle="Yeni taşıma fişi"
        onNew={() => router.replace('/hauls/new')}
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
