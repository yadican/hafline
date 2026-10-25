import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { errorMessage } from '@/api/errors';
import { colors, fontSize, spacing } from '@/theme';

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
};

export function ErrorState({ error, onRetry }: ErrorStateProps) {
  return (
    <View style={styles.centered}>
      <Text style={styles.message}>{errorMessage(error)}</Text>
      {onRetry ? <Button title="Tekrar dene" variant="secondary" onPress={onRetry} /> : null}
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
    color: colors.text,
    textAlign: 'center',
  },
  banner: {
    borderWidth: 1,
    borderColor: colors.danger,
    borderRadius: 8,
    backgroundColor: colors.dangerSurface,
    padding: spacing.md,
  },
  bannerText: {
    fontSize: fontSize.body,
    color: colors.danger,
  },
});
