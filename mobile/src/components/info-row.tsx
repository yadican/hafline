import { StyleSheet, Text, View } from 'react-native';

import { colors, fontSize, fonts, spacing } from '@/theme';

export function InfoRow({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.row}>
      <Text style={styles.label}>{label}</Text>
      <Text style={styles.value}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  row: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    paddingVertical: spacing.xs,
    borderBottomWidth: StyleSheet.hairlineWidth,
    borderBottomColor: colors.borderSoft,
  },
  label: {
    fontSize: fontSize.body,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
  value: {
    flexShrink: 1,
    fontSize: fontSize.body,
    fontFamily: fonts.bold,
    color: colors.text,
    textAlign: 'right',
  },
});
