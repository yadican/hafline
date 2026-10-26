import { StyleSheet, Text, View } from 'react-native';

import { colors, fontSize, fonts, radius, spacing } from '@/theme';

type PlateStatusProps = {
  ready: boolean;
  title: string;
  lines?: string[];
};

export function PlateStatus({ ready, title, lines = [] }: PlateStatusProps) {
  return (
    <View style={[styles.status, ready ? styles.statusReady : styles.statusWarning]}>
      <Text style={[styles.title, { color: ready ? colors.success : colors.warning }]}>{title}</Text>
      {lines.map((line) => (
        <Text key={line} style={styles.detail}>
          {line}
        </Text>
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  status: {
    gap: spacing.xs,
    padding: spacing.md,
    borderRadius: radius,
    borderWidth: 1,
    borderLeftWidth: 6,
  },
  statusReady: {
    borderColor: colors.successBorder,
    borderLeftColor: colors.create,
    backgroundColor: colors.successSurface,
  },
  statusWarning: {
    borderColor: colors.warningBorder,
    borderLeftColor: colors.primary,
    backgroundColor: colors.warningSurface,
  },
  title: {
    fontSize: fontSize.body,
    fontFamily: fonts.bold,
  },
  detail: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.text,
  },
});
