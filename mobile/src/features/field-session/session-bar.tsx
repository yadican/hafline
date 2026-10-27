import { router } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import type { FieldSession } from '@/api/types';
import { colors, fontSize, fonts, radius, spacing } from '@/theme';

type SessionBarProps = {
  session: FieldSession;
  showDumpSite?: boolean;
};

/** Where new tickets go; tickets take their site, shift and dump site from here. */
export function SessionBar({ session, showDumpSite = false }: SessionBarProps) {
  const change = () =>
    router.push(showDumpSite ? { pathname: '/session', params: { scope: 'haul' } } : { pathname: '/session' });

  return (
    <View style={styles.bar}>
      <View style={styles.text}>
        <Text style={styles.site} numberOfLines={1}>
          {session.site_name ?? '-'}
        </Text>
        <Text style={styles.detail} numberOfLines={1}>
          {showDumpSite ? `${session.shift_label}  ·  ${session.dump_site_name ?? 'Döküm yeri seçilmedi'}` : session.shift_label}
        </Text>
      </View>
      <Pressable accessibilityRole="button" onPress={change} hitSlop={12} style={styles.change}>
        <Text style={styles.changeText}>Değiştir</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  bar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
    borderRadius: radius,
    borderWidth: 1,
    borderLeftWidth: 4,
    borderColor: colors.border,
    borderLeftColor: colors.brandLine,
    backgroundColor: colors.surface,
  },
  text: {
    flex: 1,
    gap: 2,
  },
  site: {
    fontSize: fontSize.body,
    fontFamily: fonts.extrabold,
    color: colors.text,
  },
  detail: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
  change: {
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.sm,
  },
  changeText: {
    fontSize: fontSize.body,
    fontFamily: fonts.bold,
    color: colors.primaryDark,
  },
});
