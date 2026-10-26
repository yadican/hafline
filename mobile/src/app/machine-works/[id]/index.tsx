import { router, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet } from 'react-native';

import { InfoRow } from '@/components/info-row';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { SavedBanner, TicketCard } from '@/components/ticket-card';
import { useDeleteMachineWork, useMachineWorkTicket } from '@/features/machine-work/queries';
import type { ReceiptLine } from '@/features/print/receipt';
import { TicketActions } from '@/features/ticket/ticket-actions';
import { spacing } from '@/theme';
import { formatMoney } from '@/utils/number';

export default function MachineWorkTicketScreen() {
  const { id, saved } = useLocalSearchParams<{ id: string; saved?: string }>();
  const ticket = useMachineWorkTicket(id);
  const deleteWork = useDeleteMachineWork(id);

  if (ticket.isPending) {
    return <LoadingState />;
  }

  if (ticket.isError) {
    return <ErrorState error={ticket.error} onRetry={() => void ticket.refetch()} />;
  }

  const work = ticket.data;
  const lines: ReceiptLine[] = [
    { label: 'Tarih', value: work.worked_on_label },
    { label: 'Plaka', value: work.plate ?? '-' },
    { label: 'Makine', value: work.class_name ?? '-' },
    { label: 'Sahibi', value: work.owner_name ?? '-' },
    { label: 'Şantiye', value: work.site_name ?? '-' },
    { label: 'Vardiya', value: work.shift_label },
    { label: 'Saat', value: work.hours_label },
    { label: 'Saat ücreti', value: formatMoney(work.hourly_rate) },
    { label: 'Tutar', value: formatMoney(work.amount) },
    { label: 'Operatör', value: work.operator?.name ?? '-' },
    ...(work.operator_rate ? [{ label: 'Operatör ücreti', value: formatMoney(work.operator_rate) }] : []),
    ...(work.operator_amount ? [{ label: 'Operatör tutarı', value: formatMoney(work.operator_amount) }] : []),
  ];

  return (
    <ScrollView contentContainerStyle={styles.content}>
      {saved ? <SavedBanner /> : null}

      <TicketCard title={work.title} ticketNo={work.ticket_no}>
        {lines.map((line) => (
          <InfoRow key={line.label} label={line.label} value={line.value} />
        ))}
      </TicketCard>

      <TicketActions
        receipt={{ title: work.title, ticketNo: work.ticket_no, lines, qrPayload: work.qr_payload }}
        newTitle="Yeni makine fişi"
        onNew={() => router.replace('/machine-works/new')}
        onEdit={() => router.push({ pathname: '/machine-works/[id]/edit', params: { id } })}
        onDelete={() => deleteWork.mutate(undefined, { onSuccess: () => router.back() })}
        deleting={deleteWork.isPending}
        deleteError={deleteWork.error}
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
