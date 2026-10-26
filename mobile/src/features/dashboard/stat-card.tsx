import { SymbolView } from 'expo-symbols';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { DashboardCounts } from '@/api/types';
import type { IconName } from '@/components/icons';
import { colors, fontSize, fonts, radius, spacing, touchHeight } from '@/theme';

type StatCardProps = {
  title: string;
  icon: IconName;
  counts: DashboardCounts;
  onPress: () => void;
};

/** Today's count up front; yesterday and month-to-date underneath. */
export function StatCard({ title, icon, counts, onPress }: StatCardProps) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${title}: bugün ${counts.today}, dün ${counts.yesterday}, bu ay ${counts.month}`}
      onPress={onPress}
      style={({ pressed }) => [styles.card, pressed && styles.pressed]}
    >
      <View style={styles.iconWrap}>
        <SymbolView name={icon} tintColor={colors.primaryDark} size={24} />
      </View>
      <View style={styles.main}>
        <Text style={styles.title}>{title}</Text>
        <Text style={styles.detail}>
          Dün {counts.yesterday}  ·  Bu ay {counts.month}
        </Text>
      </View>
      <View style={styles.today}>
        <Text style={styles.todayValue}>{counts.today}</Text>
        <Text style={styles.todayLabel}>bugün</Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: {
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
  pressed: {
    backgroundColor: colors.selected,
  },
  iconWrap: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: colors.selected,
    alignItems: 'center',
    justifyContent: 'center',
  },
  main: {
    flex: 1,
    gap: spacing.xs,
  },
  title: {
    fontSize: fontSize.body,
    fontFamily: fonts.extrabold,
    color: colors.text,
  },
  detail: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
  today: {
    alignItems: 'flex-end',
  },
  todayValue: {
    fontSize: 28,
    fontFamily: fonts.extrabold,
    color: colors.primaryDark,
  },
  todayLabel: {
    fontSize: 12,
    fontFamily: fonts.semibold,
    color: colors.muted,
  },
});
