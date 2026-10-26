import { router } from 'expo-router';
import { SymbolView } from 'expo-symbols';
import { Pressable, StyleSheet } from 'react-native';

import { colors, spacing } from '@/theme';

import { icons } from './icons';

export function goHome() {
  if (router.canDismiss()) {
    router.dismissTo('/dashboard');

    return;
  }

  router.navigate('/dashboard');
}

/** Header shortcut back to the dashboard from any screen. */
export function HomeButton({ side = 'left' }: { side?: 'left' | 'right' }) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel="Ana sayfa"
      onPress={goHome}
      hitSlop={12}
      style={side === 'left' ? styles.left : styles.right}
    >
      <SymbolView name={icons.home} tintColor={colors.steelText} size={26} />
    </Pressable>
  );
}

const styles = StyleSheet.create({
  left: {
    marginLeft: spacing.md,
  },
  right: {
    marginRight: spacing.md,
  },
});
