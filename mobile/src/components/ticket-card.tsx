import type { ReactNode } from 'react';
import { Alert, StyleSheet, Text, View } from 'react-native';

import { errorMessage } from '@/api/errors';
import { colors, fontSize, fonts, radius, spacing } from '@/theme';

import { Button } from './button';
import { ErrorBanner } from './screen-state';

export function SavedBanner({ message = 'Fiş kaydedildi' }: { message?: string }) {
  return (
    <View style={styles.saved}>
      <Text style={styles.savedText}>{message}</Text>
    </View>
  );
}

type TicketCardProps = {
  title: string;
  ticketNo: string;
  children: ReactNode;
};

export function TicketCard({ title, ticketNo, children }: TicketCardProps) {
  return (
    <View style={styles.card}>
      <View style={styles.head}>
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.ticketNo}>{ticketNo}</Text>
      </View>
      <View style={styles.body}>{children}</View>
    </View>
  );
}

type TicketActionsProps = {
  onEdit: () => void;
  onDelete: () => void;
  deleting: boolean;
  deleteError: unknown;
};

export function TicketActions({ onEdit, onDelete, deleting, deleteError }: TicketActionsProps) {
  const confirmDelete = () => {
    Alert.alert('Fişi sil', 'Bu fiş ve cari kayıtları silinsin mi?', [
      { text: 'Vazgeç', style: 'cancel' },
      { text: 'Sil', style: 'destructive', onPress: onDelete },
    ]);
  };

  return (
    <View style={styles.actions}>
      {deleteError ? <ErrorBanner message={errorMessage(deleteError)} /> : null}
      <Button title="Düzenle" variant="edit" onPress={onEdit} disabled={deleting} />
      <Button title="Fişi sil" variant="danger" onPress={confirmDelete} loading={deleting} />
    </View>
  );
}

const styles = StyleSheet.create({
  saved: {
    padding: spacing.md,
    borderRadius: radius,
    borderWidth: 1,
    borderLeftWidth: 6,
    borderColor: colors.successBorder,
    borderLeftColor: colors.create,
    backgroundColor: colors.successSurface,
  },
  savedText: {
    fontSize: fontSize.body,
    fontFamily: fonts.bold,
    color: colors.success,
    textAlign: 'center',
  },
  card: {
    borderRadius: radius,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
    overflow: 'hidden',
  },
  head: {
    gap: spacing.xs,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.md,
    backgroundColor: colors.steel,
    borderBottomWidth: 2,
    borderBottomColor: colors.brandLine,
  },
  title: {
    fontSize: fontSize.small,
    fontFamily: fonts.bold,
    letterSpacing: 1,
    color: colors.steelText,
    textAlign: 'center',
  },
  ticketNo: {
    fontSize: fontSize.title,
    fontFamily: fonts.extrabold,
    color: colors.primaryText,
    textAlign: 'center',
  },
  body: {
    gap: spacing.xs,
    padding: spacing.md,
  },
  actions: {
    gap: spacing.sm,
  },
});
