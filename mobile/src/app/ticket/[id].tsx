import { router, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { Button } from '@/components/button';
import { InfoRow } from '@/components/info-row';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { useHaulTicket } from '@/features/haul/queries';
import { colors, fontSize, radius, spacing } from '@/theme';

export default function TicketScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const ticket = useHaulTicket(id);

  if (ticket.isPending) {
    return <LoadingState />;
  }

  if (ticket.isError) {
    return <ErrorState error={ticket.error} onRetry={() => void ticket.refetch()} />;
  }

  const goHome = () => (router.canGoBack() ? router.back() : router.replace('/'));

  return (
    <ScrollView contentContainerStyle={styles.content}>
      <View style={styles.saved}>
        <Text style={styles.savedText}>Fiş kaydedildi</Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.title}>{ticket.data.title}</Text>
        <Text style={styles.ticketNo}>{ticket.data.ticket_no}</Text>
        <View style={styles.divider} />
        <InfoRow label="Tarih" value={ticket.data.hauled_at_label} />
        <InfoRow label="Plaka" value={ticket.data.plate ?? '-'} />
        <InfoRow label="Şantiye" value={ticket.data.site_name ?? '-'} />
        <InfoRow label="Döküm yeri" value={ticket.data.dump_site_name ?? '-'} />
        <InfoRow label="Vardiya" value={ticket.data.shift_label} />
        <InfoRow label="Tonaj" value={String(ticket.data.tonnage)} />
      </View>

      <Text style={styles.hint}>Yazdırma ve PDF paylaşımı bir sonraki adımda eklenecek.</Text>

      <Button title="Yeni plaka tara" onPress={() => router.replace('/scan')} />
      <Button title="Ana sayfaya dön" variant="secondary" onPress={goHome} />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  content: {
    gap: spacing.md,
    padding: spacing.lg,
  },
  saved: {
    padding: spacing.md,
    borderRadius: radius,
    borderWidth: 1,
    borderColor: colors.success,
    backgroundColor: colors.successSurface,
  },
  savedText: {
    fontSize: fontSize.body,
    fontWeight: '700',
    color: colors.success,
    textAlign: 'center',
  },
  card: {
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  title: {
    fontSize: fontSize.body,
    fontWeight: '700',
    color: colors.text,
    textAlign: 'center',
  },
  ticketNo: {
    fontSize: fontSize.title,
    fontWeight: '700',
    color: colors.text,
    textAlign: 'center',
  },
  divider: {
    height: StyleSheet.hairlineWidth,
    backgroundColor: colors.border,
    marginVertical: spacing.xs,
  },
  hint: {
    fontSize: fontSize.small,
    color: colors.muted,
    textAlign: 'center',
  },
});
