import type { ReactNode } from 'react';
import { ScrollView, StyleSheet, Text } from 'react-native';

import { ApiError } from '@/api/errors';
import type { FieldSession } from '@/api/types';
import { useAuth } from '@/auth/auth-provider';
import { Button } from '@/components/button';
import { icons } from '@/components/icons';
import { ErrorState, LoadingState } from '@/components/screen-state';
import { colors, fontSize, fonts, spacing } from '@/theme';

import { useCurrentSession } from './queries';
import { SessionBar } from './session-bar';
import { SessionForm } from './session-form';
import { isSessionCurrent } from './shift';

type SessionGateProps = {
  /** Haul tickets cannot be written without a dump site. */
  requireDumpSite?: boolean;
  children: (session: FieldSession) => ReactNode;
};

/** Asks for site and shift (and the dump site for hauls) in place, then renders the screen. */
export function SessionGate({ requireDumpSite = false, children }: SessionGateProps) {
  const { signOut } = useAuth();
  const session = useCurrentSession();

  if (session.isPending) {
    return <LoadingState />;
  }

  if (session.isError) {
    const forbidden = session.error instanceof ApiError && session.error.status === 403;

    return forbidden ? (
      <ErrorState error={new ApiError('Bu hesabın saha fişi kesme yetkisi yok. Yöneticinize başvurun.', 403)}>
        <Button title="Çıkış yap" variant="danger" icon={icons.signOut} onPress={() => void signOut()} />
      </ErrorState>
    ) : (
      <ErrorState error={session.error} onRetry={() => void session.refetch()} />
    );
  }

  const current = session.data;

  if (!current || !isSessionCurrent(current)) {
    return (
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <Text style={styles.title}>Nerede çalışıyorsunuz?</Text>
        <Text style={styles.hint}>Fişler seçtiğiniz şantiye ve vardiyaya kesilir.</Text>
        <SessionForm initial={current} askDumpSite={requireDumpSite} submitLabel="Devam" />
      </ScrollView>
    );
  }

  if (requireDumpSite && !current.dump_site_id) {
    return (
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <SessionBar session={current} showDumpSite />
        <SessionForm initial={current} askSiteAndShift={false} askDumpSite submitLabel="Devam" />
      </ScrollView>
    );
  }

  return children(current);
}

const styles = StyleSheet.create({
  content: {
    gap: spacing.md,
    padding: spacing.lg,
  },
  title: {
    fontSize: fontSize.title,
    fontFamily: fonts.extrabold,
    color: colors.text,
  },
  hint: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
});
