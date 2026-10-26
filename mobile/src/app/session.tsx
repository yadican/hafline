import { router } from 'expo-router';
import { useState, type ReactNode } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';

import { ApiError, errorMessage } from '@/api/errors';
import type { Shift } from '@/api/types';
import { Button } from '@/components/button';
import { OptionList } from '@/components/option-list';
import { ErrorBanner, ErrorState, LoadingState } from '@/components/screen-state';
import { useCurrentSession, useDumpSites, useHaulOptions, useLockSession } from '@/features/field-session/queries';
import { colors, fontSize, fonts, spacing } from '@/theme';

export default function SessionScreen() {
  const current = useCurrentSession();
  const options = useHaulOptions();
  const lockSession = useLockSession();

  const [siteId, setSiteId] = useState<string | null>(current.data?.site_id ?? null);
  const [dumpSiteId, setDumpSiteId] = useState<string | null>(current.data?.dump_site_id ?? null);
  const [shift, setShift] = useState<Shift | null>(current.data?.shift ?? null);

  const dumpSites = useDumpSites(siteId);

  if (options.isPending) {
    return <LoadingState />;
  }

  if (options.isError) {
    const forbidden = options.error instanceof ApiError && options.error.status === 403;

    return forbidden ? (
      <ErrorState error={new ApiError('Bu hesabın saha fişi kesme yetkisi yok. Yöneticinize başvurun.', 403)} />
    ) : (
      <ErrorState error={options.error} onRetry={() => void options.refetch()} />
    );
  }

  const selectSite = (value: string) => {
    if (value !== siteId) {
      setSiteId(value);
      setDumpSiteId(null);
    }
  };

  const submit = () => {
    if (!siteId || !dumpSiteId || !shift) {
      return;
    }

    lockSession.mutate(
      { site_id: siteId, dump_site_id: dumpSiteId, shift },
      { onSuccess: () => router.back() },
    );
  };

  return (
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <Section title="1. Şantiye">
        <OptionList
          options={options.data.sites.map((site) => ({ value: site.id, label: site.name }))}
          selected={siteId}
          onSelect={selectSite}
          emptyText="Tanımlı şantiye yok."
        />
      </Section>

      <Section title="2. Döküm yeri">
        {siteId === null ? (
          <Text style={styles.hint}>Önce şantiye seçin.</Text>
        ) : dumpSites.isPending ? (
          <LoadingState />
        ) : dumpSites.isError ? (
          <ErrorBanner message={errorMessage(dumpSites.error)} />
        ) : (
          <OptionList
            options={dumpSites.data.map((site) => ({ value: site.id, label: site.name }))}
            selected={dumpSiteId}
            onSelect={setDumpSiteId}
            emptyText="Bu şantiye için aktif güzergahı olan döküm yeri yok."
          />
        )}
      </Section>

      <Section title="3. Vardiya">
        <OptionList
          options={options.data.shifts.map((item) => ({ value: item.value, label: item.label }))}
          selected={shift}
          onSelect={(value) => setShift(value as Shift)}
          emptyText="Vardiya tanımı yok."
        />
      </Section>

      {lockSession.isError ? <ErrorBanner message={errorMessage(lockSession.error)} /> : null}

      <Button
        title="Oturumu başlat"
        onPress={submit}
        loading={lockSession.isPending}
        disabled={!siteId || !dumpSiteId || !shift}
      />
    </ScrollView>
  );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
  return (
    <View style={styles.section}>
      <Text style={styles.sectionTitle}>{title}</Text>
      {children}
    </View>
  );
}

const styles = StyleSheet.create({
  content: {
    gap: spacing.lg,
    padding: spacing.lg,
  },
  section: {
    gap: spacing.sm,
  },
  sectionTitle: {
    fontSize: fontSize.body,
    fontFamily: fonts.extrabold,
    color: colors.text,
  },
  hint: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
});
