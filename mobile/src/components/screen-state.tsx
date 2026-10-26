import type { ReactNode } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { errorMessage } from '@/api/errors';
import { colors, fontSize, fonts, radius, spacing } from '@/theme';

import { Button } from './button';

export function LoadingState() {
  return (
    <View style={styles.centered}>
      <ActivityIndicator size="large" color={colors.primary} />
    </View>
  );
}

type ErrorStateProps = {
  error: unknown;
  onRetry?: () => void;
  children?: ReactNode;
};

export function ErrorState({ error, onRetry, children }: ErrorStateProps) {
  return (
    <View style={styles.centered}>
      <Text style={styles.message}>{errorMessage(error)}</Text>
      {onRetry ? <Button title="Tekrar dene" variant="secondary" onPress={onRetry} /> : null}
      {children}
    </View>
  );
}

export function ErrorBanner({ message }: { message: string }) {
  return (
    <View style={styles.banner} accessibilityRole="alert">
      <Text style={styles.bannerText}>{message}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  centered: {
    flex: 1,
    alignItems: 'stretch',
    justifyContent: 'center',
    gap: spacing.md,
    padding: spacing.lg,
    backgroundColor: colors.background,
  },
  message: {
    fontSize: fontSize.body,
    fontFamily: fonts.semibold,
    color: colors.text,
    textAlign: 'center',
  },
  banner: {
    borderWidth: 1,
    borderLeftWidth: 6,
    borderColor: colors.dangerBorder,
    borderLeftColor: colors.danger,
    borderRadius: radius,
    backgroundColor: colors.dangerSurface,
    padding: spacing.md,
  },
  bannerText: {
    fontSize: fontSize.body,
    fontFamily: fonts.semibold,
    color: colors.dangerText,
  },
});
