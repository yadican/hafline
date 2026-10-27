import { useState } from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

import { errorMessage } from '@/api/errors';
import type { FieldSession, Shift } from '@/api/types';
import { Button } from '@/components/button';
import { icons } from '@/components/icons';
import { FormSection } from '@/components/form-section';
import { ErrorBanner, LoadingState } from '@/components/screen-state';
import { SelectField } from '@/components/select-field';
import { colors, fontSize, fonts, radius, spacing, touchHeight } from '@/theme';

import { useDumpSites, useHaulOptions, useLockSession } from './queries';
import { isSessionCurrent, suggestedShift } from './shift';

type SessionFormProps = {
  initial: FieldSession | null;
  /** False when only the dump site is missing and site / shift are already chosen. */
  askSiteAndShift?: boolean;
  /** Haul tickets need a dump site; machine and fuel tickets do not. */
  askDumpSite: boolean;
  submitLabel: string;
  onSaved?: () => void;
};

export function SessionForm({ initial, askSiteAndShift = true, askDumpSite, submitLabel, onSaved }: SessionFormProps) {
  const options = useHaulOptions();
  const lockSession = useLockSession();

  const [siteId, setSiteId] = useState<string | null>(initial?.site_id ?? null);
  const [shift, setShift] = useState<Shift>(initial && isSessionCurrent(initial) ? initial.shift : suggestedShift());
  const [dumpSiteId, setDumpSiteId] = useState<string | null>(initial?.dump_site_id ?? null);

  const dumpSites = useDumpSites(askDumpSite ? siteId : null);
  const sameSite = siteId !== null && siteId === initial?.site_id;

  if (options.isPending) {
    return <LoadingState />;
  }

  if (options.isError) {
    return (
      <View style={styles.form}>
        <ErrorBanner message={errorMessage(options.error)} />
        <Button title="Tekrar dene" variant="secondary" icon={icons.retry} onPress={() => void options.refetch()} />
      </View>
    );
  }

  const selectSite = (value: string) => {
    if (value !== siteId) {
      setSiteId(value);
      setDumpSiteId(value === initial?.site_id ? (initial?.dump_site_id ?? null) : null);
    }
  };

  const ready = siteId !== null && (!askDumpSite || dumpSiteId !== null);

  const submit = () => {
    if (!siteId) {
      return;
    }

    // A dump site belongs to its site; keep it only while the site stays the same.
    const dumpSite = askDumpSite ? dumpSiteId : sameSite ? (initial?.dump_site_id ?? null) : null;

    lockSession.mutate({ site_id: siteId, shift, dump_site_id: dumpSite }, { onSuccess: () => onSaved?.() });
  };

  return (
    <View style={styles.form}>
      {askSiteAndShift ? (
        <>
          <FormSection title="Şantiye">
            <SelectField
              title="Şantiye seçin"
              placeholder="Şantiye seçin"
              options={options.data.sites.map((site) => ({ value: site.id, label: site.name }))}
              selected={siteId}
              onSelect={selectSite}
              emptyText="Tanımlı şantiye yok."
            />
          </FormSection>

          <FormSection title="Vardiya">
            <View style={styles.choices}>
              {options.data.shifts.map((item) => {
                const selected = item.value === shift;

                return (
                  <Pressable
                    key={item.value}
                    accessibilityRole="radio"
                    accessibilityState={{ selected }}
                    onPress={() => setShift(item.value)}
                    style={({ pressed }) => [styles.choice, selected && styles.choiceSelected, pressed && styles.choicePressed]}
                  >
                    <Text style={[styles.choiceText, selected && styles.choiceTextSelected]}>{item.label}</Text>
                  </Pressable>
                );
              })}
            </View>
          </FormSection>
        </>
      ) : null}

      {askDumpSite ? (
        <FormSection title="Döküm yeri" hint="Taşıma fişleri bu döküm yerine kesilir.">
          {siteId === null ? (
            <Text style={styles.hint}>Önce şantiye seçin.</Text>
          ) : dumpSites.isPending ? (
            <LoadingState />
          ) : dumpSites.isError ? (
            <ErrorBanner message={errorMessage(dumpSites.error)} />
          ) : (
            <SelectField
              title="Döküm yeri seçin"
              placeholder="Döküm yeri seçin"
              options={dumpSites.data.map((site) => ({ value: site.id, label: site.name }))}
              selected={dumpSiteId}
              onSelect={setDumpSiteId}
              emptyText="Bu şantiye için aktif güzergahı olan döküm yeri yok."
            />
          )}
        </FormSection>
      ) : null}

      {lockSession.isError ? <ErrorBanner message={errorMessage(lockSession.error)} /> : null}

      <Button title={submitLabel} icon={icons.save} onPress={submit} loading={lockSession.isPending} disabled={!ready} />
    </View>
  );
}

const styles = StyleSheet.create({
  form: {
    gap: spacing.lg,
  },
  hint: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
  choices: {
    flexDirection: 'row',
    gap: spacing.sm,
  },
  choice: {
    flex: 1,
    minHeight: touchHeight,
    alignItems: 'center',
    justifyContent: 'center',
    borderRadius: radius,
    borderWidth: 1,
    borderColor: colors.inputBorder,
    backgroundColor: colors.surface,
  },
  choiceSelected: {
    borderColor: colors.primary,
    backgroundColor: colors.selected,
  },
  choicePressed: {
    backgroundColor: colors.surfaceMuted,
  },
  choiceText: {
    fontSize: fontSize.body,
    fontFamily: fonts.semibold,
    color: colors.text,
  },
  choiceTextSelected: {
    fontFamily: fonts.bold,
    color: colors.primaryDark,
  },
});
