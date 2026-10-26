import type { ReactElement } from 'react';
import { FlatList, Pressable, RefreshControl, StyleSheet, Text, View } from 'react-native';

import { colors, fontSize, fonts, radius, spacing, touchHeight } from '@/theme';

export type TicketListItem = {
  id: string;
  title: string;
  subtitle: string;
  value?: string;
  valueDetail?: string;
};

type TicketListProps = {
  items: TicketListItem[];
  onPress: (id: string) => void;
  emptyText: string;
  refreshing: boolean;
  onRefresh: () => void;
  header?: ReactElement;
};

export function TicketList({ items, onPress, emptyText, refreshing, onRefresh, header }: TicketListProps) {
  return (
    <FlatList
      data={items}
      keyExtractor={(item) => item.id}
      contentContainerStyle={styles.content}
      ListHeaderComponent={header}
      ListEmptyComponent={<Text style={styles.empty}>{emptyText}</Text>}
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={onRefresh} colors={[colors.primary]} />}
      renderItem={({ item }) => (
        <Pressable
          accessibilityRole="button"
          onPress={() => onPress(item.id)}
          style={({ pressed }) => [styles.row, pressed && styles.rowPressed]}
        >
          <View style={styles.main}>
            <Text style={styles.title}>{item.title}</Text>
            <Text style={styles.subtitle}>{item.subtitle}</Text>
          </View>
          {item.value || item.valueDetail ? (
            <View style={styles.side}>
              {item.value ? <Text style={styles.value}>{item.value}</Text> : null}
              {item.valueDetail ? <Text style={styles.amount}>{item.valueDetail}</Text> : null}
            </View>
          ) : null}
        </Pressable>
      )}
    />
  );
}

const styles = StyleSheet.create({
  content: {
    gap: spacing.sm,
    padding: spacing.lg,
  },
  row: {
    minHeight: touchHeight,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    padding: spacing.md,
    borderRadius: radius,
    borderWidth: 1,
    borderLeftWidth: 4,
    borderColor: colors.border,
    borderLeftColor: colors.brandLine,
    backgroundColor: colors.surface,
  },
  rowPressed: {
    backgroundColor: colors.selected,
  },
  main: {
    flex: 1,
    gap: spacing.xs,
  },
  side: {
    alignItems: 'flex-end',
    gap: spacing.xs,
  },
  title: {
    fontSize: fontSize.body,
    fontFamily: fonts.extrabold,
    letterSpacing: 0.3,
    color: colors.text,
  },
  subtitle: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
  value: {
    fontSize: fontSize.body,
    fontFamily: fonts.bold,
    color: colors.text,
  },
  amount: {
    fontSize: fontSize.small,
    fontFamily: fonts.bold,
    color: colors.primaryDark,
  },
  empty: {
    padding: spacing.md,
    fontSize: fontSize.body,
    fontFamily: fonts.medium,
    color: colors.muted,
    textAlign: 'center',
  },
});
