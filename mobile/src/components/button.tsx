import { ActivityIndicator, Pressable, StyleSheet, Text } from 'react-native';

import { colors, fontSize, radius, spacing, touchHeight } from '@/theme';

type Variant = 'primary' | 'secondary' | 'danger';

type ButtonProps = {
  title: string;
  onPress: () => void;
  variant?: Variant;
  loading?: boolean;
  disabled?: boolean;
};

export function Button({ title, onPress, variant = 'primary', loading = false, disabled = false }: ButtonProps) {
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
        { backgroundColor: palette.background, borderColor: palette.border },
        pressed && styles.pressed,
        inactive && styles.inactive,
      ]}
    >
      {loading ? (
        <ActivityIndicator color={palette.text} />
      ) : (
        <Text style={[styles.title, { color: palette.text }]}>{title}</Text>
      )}
    </Pressable>
  );
}

const PALETTES: Record<Variant, { background: string; border: string; text: string }> = {
  primary: { background: colors.primary, border: colors.primary, text: colors.primaryText },
  secondary: { background: colors.surface, border: colors.border, text: colors.text },
  danger: { background: colors.surface, border: colors.danger, text: colors.danger },
};

const styles = StyleSheet.create({
  base: {
    minHeight: touchHeight,
    borderRadius: radius,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
  },
  pressed: {
    opacity: 0.8,
  },
  inactive: {
    opacity: 0.5,
  },
  title: {
    fontSize: fontSize.body,
    fontWeight: '600',
  },
});
