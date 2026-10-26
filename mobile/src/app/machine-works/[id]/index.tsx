import { router, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet } from 'react-native';

import { Button } from '@/components/button';
import { InfoRow } from '@/components/info-row';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { SavedBanner, TicketActions, TicketCard } from '@/components/ticket-card';
import { useDeleteMachineWork, useMachineWorkTicket } from '@/features/machine-work/queries';
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

  return (
    <ScrollView contentContainerStyle={styles.content}>
      {saved ? <SavedBanner /> : null}

      <TicketCard title={work.title} ticketNo={work.ticket_no}>
        <InfoRow label="Tarih" value={work.worked_on_label} />
        <InfoRow label="Plaka" value={work.plate ?? '-'} />
        <InfoRow label="Makine" value={work.class_name ?? '-'} />
        <InfoRow label="Sahibi" value={work.owner_name ?? '-'} />
        <InfoRow label="Şantiye" value={work.site_name ?? '-'} />
        <InfoRow label="Vardiya" value={work.shift_label} />
        <InfoRow label="Saat" value={work.hours_label} />
        <InfoRow label="Saat ücreti" value={formatMoney(work.hourly_rate)} />
        <InfoRow label="Tutar" value={formatMoney(work.amount)} />
        <InfoRow label="Operatör" value={work.operator?.name ?? '-'} />
        {work.operator_rate ? <InfoRow label="Operatör ücreti" value={formatMoney(work.operator_rate)} /> : null}
        {work.operator_amount ? <InfoRow label="Operatör tutarı" value={formatMoney(work.operator_amount)} /> : null}
      </TicketCard>

      <Button title="Yeni makine fişi" variant="create" onPress={() => router.replace('/machine-works/new')} />
      <TicketActions
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
