import { router, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet } from 'react-native';

import { Button } from '@/components/button';
import { InfoRow } from '@/components/info-row';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { SavedBanner, TicketActions, TicketCard } from '@/components/ticket-card';
import { useDeleteFuelReceipt, useFuelReceiptTicket } from '@/features/fuel-receipt/queries';
import { spacing } from '@/theme';
import { decimalForInput, formatMoney } from '@/utils/number';

export default function FuelReceiptTicketScreen() {
  const { id, saved } = useLocalSearchParams<{ id: string; saved?: string }>();
  const ticket = useFuelReceiptTicket(id);
  const deleteReceipt = useDeleteFuelReceipt(id);

  if (ticket.isPending) {
    return <LoadingState />;
  }

  if (ticket.isError) {
    return <ErrorState error={ticket.error} onRetry={() => void ticket.refetch()} />;
  }

  const receipt = ticket.data;

  return (
    <ScrollView contentContainerStyle={styles.content}>
      {saved ? <SavedBanner /> : null}

      <TicketCard title={receipt.title} ticketNo={receipt.ticket_no}>
        <InfoRow label="Tarih" value={receipt.issued_on_label} />
        <InfoRow label="Plaka" value={receipt.plate ?? '-'} />
        <InfoRow label="Sahibi" value={receipt.owner_name ?? '-'} />
        <InfoRow label="Şantiye" value={receipt.site_name ?? '-'} />
        <InfoRow label="Vardiya" value={receipt.shift_label ?? '-'} />
        <InfoRow label="Yakıt yeri" value={receipt.station?.name ?? '-'} />
        <InfoRow label="Firma" value={receipt.firm_name ?? '-'} />
        <InfoRow label="Litre" value={receipt.quantity_label} />
        <InfoRow label="Birim fiyat" value={`${decimalForInput(receipt.unit_price)} ₺`} />
        <InfoRow label="Tutar" value={formatMoney(receipt.amount)} />
        <InfoRow label="Kilometre" value={receipt.km != null ? String(receipt.km) : '-'} />
        <InfoRow label="Alan kişi" value={receipt.received_by ?? '-'} />
        <InfoRow
          label="Taşıma fiş adedi"
          value={receipt.haul_ticket_count != null ? String(receipt.haul_ticket_count) : '-'}
        />
      </TicketCard>

      <Button title="Yeni yakıt fişi" variant="create" onPress={() => router.replace('/fuel-receipts/new')} />
      <TicketActions
        onEdit={() => router.push({ pathname: '/fuel-receipts/[id]/edit', params: { id } })}
        onDelete={() => deleteReceipt.mutate(undefined, { onSuccess: () => router.back() })}
        deleting={deleteReceipt.isPending}
        deleteError={deleteReceipt.error}
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
