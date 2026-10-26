import { router, useFocusEffect } from 'expo-router';
import { useCallback, useRef } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import type { DashboardRecentItem } from '@/api/types';
import { ActionTile, ActionTileRow } from '@/components/action-tile';
import { Button } from '@/components/button';
import { icons, type AppIconName } from '@/components/icons';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { TicketList } from '@/components/ticket-list';
import { useDashboard } from '@/features/dashboard/queries';
import { StatCard } from '@/features/dashboard/stat-card';
import { SessionBar } from '@/features/field-session/session-bar';
import { isSessionCurrent } from '@/features/field-session/shift';
import { colors, fontSize, fonts, radius, spacing, ticketTones, type TicketType } from '@/theme';

const TYPE_ICONS: Record<TicketType, AppIconName> = {
  haul: icons.hauls,
  machine_work: icons.machineWorks,
  fuel_receipt: icons.fuelReceipts,
};

function openTicket(item: DashboardRecentItem) {
  const params = { id: item.id };

  switch (item.type) {
    case 'haul':
      router.push({ pathname: '/hauls/[id]', params });
      break;
    case 'machine_work':
      router.push({ pathname: '/machine-works/[id]', params });
      break;
    case 'fuel_receipt':
      router.push({ pathname: '/fuel-receipts/[id]', params });
      break;
  }
}

export default function DashboardScreen() {
  const dashboard = useDashboard();
  const { refetch } = dashboard;
  const firstFocus = useRef(true);

  // Tickets are written on other screens; refresh the counts whenever the user comes back.
  useFocusEffect(
    useCallback(() => {
      if (firstFocus.current) {
        firstFocus.current = false;

        return;
      }

      void refetch();
    }, [refetch]),
  );

  if (dashboard.isPending) {
    return <LoadingState />;
  }

  if (dashboard.isError) {
    return <ErrorState error={dashboard.error} onRetry={() => void dashboard.refetch()} />;
  }

  const { field_session: session, counts, recent } = dashboard.data;
  const sessionReady = session !== null && isSessionCurrent(session);

  return (
    <TicketList
      items={recent.map((item) => ({
        id: item.id,
        title: item.plate ?? '-',
        subtitle: item.summary,
        value: item.time,
        icon: {
          name: TYPE_ICONS[item.type],
          color: ticketTones[item.type].icon,
          background: ticketTones[item.type].background,
        },
      }))}
      onPress={(id) => {
        const item = recent.find((candidate) => candidate.id === id);

        if (item) {
          openTicket(item);
        }
      }}
      emptyText="Bugün henüz fiş kesmediniz."
      refreshing={dashboard.isRefetching}
      onRefresh={() => void dashboard.refetch()}
      header={
        <View style={styles.header}>
          {sessionReady ? (
            <SessionBar session={session} showDumpSite={session.dump_site_id !== null} />
          ) : (
            <View style={styles.noSession}>
              <Text style={styles.noSessionText}>Şantiye ve vardiya seçilmedi.</Text>
              <Button title="Şantiye ve vardiya seç" onPress={() => router.push('/session')} />
            </View>
          )}

          <StatCard
            label="Taşıma fişleri"
            icon={TYPE_ICONS.haul}
            tone={ticketTones.haul}
            counts={counts.hauls}
            onPress={() => router.push('/hauls')}
          />
          <StatCard
            label="Makine fişleri"
            icon={TYPE_ICONS.machine_work}
            tone={ticketTones.machine_work}
            counts={counts.machine_works}
            onPress={() => router.push('/machine-works')}
          />
          <StatCard
            label="Yakıt fişleri"
            icon={TYPE_ICONS.fuel_receipt}
            tone={ticketTones.fuel_receipt}
            counts={counts.fuel_receipts}
            onPress={() => router.push('/fuel-receipts')}
          />

          <ActionTileRow>
            <ActionTile
              title="Yeni fiş"
              icon={TYPE_ICONS.haul}
              tone={ticketTones.haul}
              accessibilityLabel="Yeni taşıma fişi"
              onPress={() => router.push('/hauls/new')}
            />
            <ActionTile
              title="Yeni fiş"
              icon={TYPE_ICONS.machine_work}
              tone={ticketTones.machine_work}
              accessibilityLabel="Yeni makine fişi"
              onPress={() => router.push('/machine-works/new')}
            />
            <ActionTile
              title="Yeni fiş"
              icon={TYPE_ICONS.fuel_receipt}
              tone={ticketTones.fuel_receipt}
              accessibilityLabel="Yeni yakıt fişi"
              onPress={() => router.push('/fuel-receipts/new')}
            />
          </ActionTileRow>

          <Text style={styles.sectionTitle}>Bugün kestiğim son fişler</Text>
        </View>
      }
    />
  );
}

const styles = StyleSheet.create({
  header: {
    gap: spacing.sm,
    marginBottom: spacing.sm,
  },
  noSession: {
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius,
    borderWidth: 1,
    borderLeftWidth: 4,
    borderColor: colors.warningBorder,
    borderLeftColor: colors.warning,
    backgroundColor: colors.warningSurface,
  },
  noSessionText: {
    fontSize: fontSize.body,
    fontFamily: fonts.semibold,
    color: colors.warning,
  },
  sectionTitle: {
    marginTop: spacing.sm,
    fontSize: fontSize.small,
    fontFamily: fonts.bold,
    letterSpacing: 0.5,
    color: colors.muted,
  },
});
