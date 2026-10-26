import { router, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet } from 'react-native';

import { Button } from '@/components/button';
import { InfoRow } from '@/components/info-row';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { SavedBanner, TicketActions, TicketCard } from '@/components/ticket-card';
import { useDeleteFuelReceipt, useFuelReceiptTicket } from '@/features/fuel-receipt/queries';
import { PrintActions } from '@/features/print/print-actions';
import type { ReceiptLine } from '@/features/print/receipt';
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
  const lines: ReceiptLine[] = [
    { label: 'Tarih', value: receipt.issued_on_label },
    { label: 'Plaka', value: receipt.plate ?? '-' },
    { label: 'Sahibi', value: receipt.owner_name ?? '-' },
    { label: 'Şantiye', value: receipt.site_name ?? '-' },
    { label: 'Vardiya', value: receipt.shift_label ?? '-' },
    { label: 'Yakıt yeri', value: receipt.station?.name ?? '-' },
    { label: 'Firma', value: receipt.firm_name ?? '-' },
    { label: 'Litre', value: receipt.quantity_label },
    { label: 'Birim fiyat', value: `${decimalForInput(receipt.unit_price)} ₺` },
    { label: 'Tutar', value: formatMoney(receipt.amount) },
    { label: 'Kilometre', value: receipt.km != null ? String(receipt.km) : '-' },
    { label: 'Alan kişi', value: receipt.received_by ?? '-' },
    {
      label: 'Taşıma fiş adedi',
      value: receipt.haul_ticket_count != null ? String(receipt.haul_ticket_count) : '-',
    },
  ];

  return (
    <ScrollView contentContainerStyle={styles.content}>
      {saved ? <SavedBanner /> : null}

      <TicketCard title={receipt.title} ticketNo={receipt.ticket_no}>
        {lines.map((line) => (
          <InfoRow key={line.label} label={line.label} value={line.value} />
        ))}
      </TicketCard>

      <PrintActions
        receipt={{ title: receipt.title, ticketNo: receipt.ticket_no, lines, qrPayload: receipt.qr_payload }}
      />
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
