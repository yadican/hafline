import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { DashboardCounts } from '@/api/types';
import { AppIcon } from '@/components/app-icon';
import type { AppIconName } from '@/components/icons';
import { colors, fontSize, fonts, radius, spacing, touchHeight } from '@/theme';

type StatCardProps = {
  /** Screen readers only; the coloured icon tells the type on screen. */
  label: string;
  icon: AppIconName;
  tone: { background: string; icon: string };
  counts: DashboardCounts;
  onPress: () => void;
};

/** Today's count up front; yesterday and month-to-date underneath. */
export function StatCard({ label, icon, tone, counts, onPress }: StatCardProps) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${label}: bugün ${counts.today}, dün ${counts.yesterday}, bu ay ${counts.month}`}
      onPress={onPress}
      style={({ pressed }) => [styles.card, { borderLeftColor: tone.icon }, pressed && styles.pressed]}
    >
      <View style={[styles.iconWrap, { backgroundColor: tone.background }]}>
        <AppIcon name={icon} color={tone.icon} size={32} />
      </View>
      <View style={styles.main}>
        <Text style={styles.detail}>Dün {counts.yesterday}</Text>
        <Text style={styles.detail}>Bu ay {counts.month}</Text>
      </View>
      <View style={styles.today}>
        <Text style={[styles.todayValue, { color: tone.icon }]}>{counts.today}</Text>
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
    backgroundColor: colors.surface,
  },
  pressed: {
    backgroundColor: colors.surfaceMuted,
  },
  iconWrap: {
    width: 52,
    height: 52,
    borderRadius: 26,
    alignItems: 'center',
    justifyContent: 'center',
  },
  main: {
    flex: 1,
    gap: spacing.xs,
  },
  detail: {
    fontSize: fontSize.small,
    fontFamily: fonts.semibold,
    color: colors.muted,
  },
  today: {
    alignItems: 'flex-end',
  },
  todayValue: {
    fontSize: 28,
    fontFamily: fonts.extrabold,
  },
  todayLabel: {
    fontSize: 12,
    fontFamily: fonts.semibold,
    color: colors.muted,
  },
});
