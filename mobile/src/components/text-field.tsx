import { useState } from 'react';
import { StyleSheet, Text, TextInput, View, type TextInputProps } from 'react-native';

import { colors, fontSize, fonts, radius, spacing, touchHeight } from '@/theme';

type TextFieldProps = TextInputProps & {
  label: string;
  error?: string;
  /** "dark" matches the back office login screen. */
  appearance?: 'light' | 'dark';
};

export function TextField({ label, error, appearance = 'light', style, onFocus, onBlur, ...inputProps }: TextFieldProps) {
  const [focused, setFocused] = useState(false);
  const dark = appearance === 'dark';

  return (
    <View style={styles.container}>
      <Text style={[styles.label, dark && styles.labelDark]}>{label}</Text>
      <TextInput
        placeholderTextColor={dark ? 'rgba(230, 235, 232, 0.3)' : colors.placeholder}
        selectionColor={colors.primary}
        cursorColor={colors.primary}
        style={[
          styles.input,
          dark && styles.inputDark,
          focused && (dark ? styles.inputDarkFocused : styles.inputFocused),
          error ? styles.inputError : null,
          style,
        ]}
        onFocus={(event) => {
          setFocused(true);
          onFocus?.(event);
        }}
        onBlur={(event) => {
          setFocused(false);
          onBlur?.(event);
        }}
        {...inputProps}
      />
      {error ? <Text style={[styles.error, dark && styles.errorDark]}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: spacing.xs,
  },
  label: {
    fontSize: fontSize.small,
    fontFamily: fonts.bold,
    color: colors.muted,
    letterSpacing: 0.2,
  },
  labelDark: {
    fontSize: 12,
    letterSpacing: 1,
    textTransform: 'uppercase',
    color: 'rgba(230, 235, 232, 0.55)',
  },
  input: {
    minHeight: touchHeight,
    borderWidth: 1,
    borderColor: colors.inputBorder,
    borderRadius: radius,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
    fontSize: fontSize.body,
    fontFamily: fonts.medium,
    color: colors.text,
  },
  inputFocused: {
    borderColor: colors.primary,
  },
  inputDark: {
    borderColor: 'rgba(255, 255, 255, 0.15)',
    backgroundColor: 'rgba(255, 255, 255, 0.05)',
    color: colors.primaryText,
  },
  inputDarkFocused: {
    borderColor: colors.brandLine,
    backgroundColor: 'rgba(196, 137, 46, 0.1)',
  },
  inputError: {
    borderColor: colors.danger,
  },
  error: {
    fontSize: fontSize.small,
    fontFamily: fonts.semibold,
    color: colors.danger,
  },
  errorDark: {
    color: '#ffd0d0',
  },
});
