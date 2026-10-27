import { SymbolView } from 'expo-symbols';
import { ActivityIndicator, Pressable, StyleSheet, Text } from 'react-native';

import { colors, fontSize, fonts, radius, spacing, touchHeight } from '@/theme';

import type { IconName } from './icons';

/**
 * One filled button per screen: primary = the main action (brand ochre).
 * secondary = neutral alternative, danger = soft destructive (sign out),
 * dangerText = low-emphasis destructive (delete at the bottom of a screen).
 */
type Variant = 'primary' | 'secondary' | 'danger' | 'dangerText';

type ButtonProps = {
  title: string;
  onPress: () => void;
  variant?: Variant;
  icon?: IconName;
  loading?: boolean;
  disabled?: boolean;
};

export function Button({ title, onPress, variant = 'primary', icon, loading = false, disabled = false }: ButtonProps) {
  const inactive = disabled || loading;
  const palette = PALETTES[variant];

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: inactive, busy: loading }}
      disabled={inactive}
      onPress={onPress}
      style={({ pressed }) => [
        styles.base,
        {
          backgroundColor: pressed ? palette.pressed : palette.background,
          borderColor: palette.border,
        },
        inactive && styles.inactive,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={palette.text} />
      ) : (
        <>
          {icon ? <SymbolView name={icon} tintColor={palette.icon} size={20} /> : null}
          <Text style={[styles.title, { color: palette.text }]}>{title}</Text>
        </>
      )}
    </Pressable>
  );
}

type Palette = { background: string; pressed: string; border: string; text: string; icon: string };

const PALETTES: Record<Variant, Palette> = {
  primary: {
    background: colors.primary,
    pressed: colors.primaryDark,
    border: colors.primary,
    text: colors.primaryText,
    icon: colors.primaryText,
  },
  secondary: {
    background: colors.surface,
    pressed: colors.surfaceMuted,
    border: colors.border,
    text: colors.text,
    icon: colors.primaryDark,
  },
  danger: {
    background: colors.dangerSurface,
    pressed: colors.dangerBorder,
    border: colors.dangerBorder,
    text: colors.danger,
    icon: colors.danger,
  },
  dangerText: {
    background: 'transparent',
    pressed: colors.dangerSurface,
    border: 'transparent',
    text: colors.danger,
    icon: colors.danger,
  },
};

const styles = StyleSheet.create({
  base: {
    minHeight: touchHeight,
    borderRadius: radius,
    borderWidth: 1,
    flexDirection: 'row',
    gap: spacing.sm,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
  },
  inactive: {
    opacity: 0.5,
  },
  title: {
    fontSize: fontSize.body,
    fontFamily: fonts.bold,
  },
});
