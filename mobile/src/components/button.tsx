import { ActivityIndicator, Pressable, StyleSheet, Text } from 'react-native';

import { colors, fontSize, fonts, radius, spacing, touchHeight } from '@/theme';

/**
 * Same colour language as the back office (.panel-btn-*):
 * primary = brand ochre, create = new record, edit = update, danger = delete, secondary = neutral.
 */
type Variant = 'primary' | 'secondary' | 'create' | 'edit' | 'danger';

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
        <Text style={[styles.title, { color: palette.text }]}>{title}</Text>
      )}
    </Pressable>
  );
}

const PALETTES: Record<Variant, { background: string; pressed: string; border: string; text: string }> = {
  primary: { background: colors.primary, pressed: colors.primaryDark, border: colors.primaryDark, text: colors.primaryText },
  secondary: { background: colors.surface, pressed: colors.surfaceMuted, border: colors.border, text: colors.text },
  create: { background: colors.create, pressed: colors.createDark, border: colors.createDark, text: colors.primaryText },
  edit: { background: colors.edit, pressed: colors.editDark, border: colors.editDark, text: colors.primaryText },
  danger: { background: colors.danger, pressed: colors.dangerDark, border: colors.dangerDark, text: colors.primaryText },
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
  inactive: {
    opacity: 0.5,
  },
  title: {
    fontSize: fontSize.body,
    fontFamily: fonts.bold,
  },
});
