import type { ReactNode } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import { colors, fonts, radius, spacing } from '@/theme';

import { AppIcon } from './app-icon';
import type { AppIconName } from './icons';

type ActionTileProps = {
  title: string;
  icon: AppIconName;
  onPress: () => void;
  loading?: boolean;
  disabled?: boolean;
  tone?: { background: string; icon: string };
  /** Defaults to the title; set when the icon carries meaning the title does not. */
  accessibilityLabel?: string;
};

/** Icon-over-label secondary action, laid out in rows of equal-width tiles. */
export function ActionTile({
  title,
  icon,
  onPress,
  loading = false,
  disabled = false,
  tone,
  accessibilityLabel,
}: ActionTileProps) {
  const iconColor = tone?.icon ?? colors.primaryDark;
  const inactive = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel ?? title}
      accessibilityState={{ disabled: inactive, busy: loading }}
      disabled={inactive}
      onPress={onPress}
      style={({ pressed }) => [styles.tile, pressed && styles.pressed, inactive && styles.inactive]}
    >
      <View style={[styles.iconWrap, tone && { backgroundColor: tone.background }]}>
        {loading ? <ActivityIndicator color={iconColor} /> : <AppIcon name={icon} color={iconColor} size={26} />}
      </View>
      <Text style={styles.title} numberOfLines={1} adjustsFontSizeToFit>
        {title}
      </Text>
    </Pressable>
  );
}

export function ActionTileRow({ children }: { children: ReactNode }) {
  return <View style={styles.row}>{children}</View>;
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  tile: {
    flex: 1,
    alignItems: 'center',
    gap: spacing.sm,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.xs,
    borderRadius: radius,
    borderWidth: 1,
    borderColor: colors.borderSoft,
    backgroundColor: colors.surface,
  },
  pressed: {
    backgroundColor: colors.surfaceMuted,
    borderColor: colors.border,
  },
  inactive: {
    opacity: 0.5,
  },
  iconWrap: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: colors.selected,
    alignItems: 'center',
    justifyContent: 'center',
  },
  title: {
    fontSize: 13,
    fontFamily: fonts.semibold,
    color: colors.text,
  },
});
