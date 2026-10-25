import { StyleSheet, Text, TextInput, View, type TextInputProps } from 'react-native';

import { colors, fontSize, radius, spacing, touchHeight } from '@/theme';

type TextFieldProps = TextInputProps & {
  label: string;
  error?: string;
};

export function TextField({ label, error, style, ...inputProps }: TextFieldProps) {
  return (
    <View style={styles.container}>
      <Text style={styles.label}>{label}</Text>
      <TextInput
        placeholderTextColor={colors.muted}
        style={[styles.input, error ? styles.inputError : null, style]}
        {...inputProps}
      />
      {error ? <Text style={styles.error}>{error}</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: spacing.xs,
  },
  label: {
    fontSize: fontSize.small,
    fontWeight: '600',
    color: colors.text,
  },
  input: {
    minHeight: touchHeight,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: radius,
    backgroundColor: colors.surface,
    paddingHorizontal: spacing.md,
    fontSize: fontSize.body,
    color: colors.text,
  },
  inputError: {
    borderColor: colors.danger,
  },
  error: {
    fontSize: fontSize.small,
    color: colors.danger,
  },
});
