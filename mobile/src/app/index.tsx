import { router } from 'expo-router';
import { Alert, ScrollView, StyleSheet, Text, View } from 'react-native';

import { ApiError, errorMessage } from '@/api/errors';
import type { FieldSession } from '@/api/types';
import { useAuth, useSignedInUser } from '@/auth/auth-provider';
import { Button } from '@/components/button';
import { InfoRow } from '@/components/info-row';
import { ErrorBanner, ErrorState, LoadingState } from '@/components/screen-state';
import { useCloseSession, useCurrentSession } from '@/features/field-session/queries';
import { colors, fontSize, radius, spacing } from '@/theme';

export default function HomeScreen() {
  const user = useSignedInUser();
  const { signOut } = useAuth();
  const session = useCurrentSession();
  const closeSession = useCloseSession();

  if (session.isPending) {
    return <LoadingState />;
  }

  if (session.isError) {
    const forbidden = session.error instanceof ApiError && session.error.status === 403;

    return (
      <ErrorState
        error={
          forbidden
            ? new ApiError('Bu hesabın saha fişi kesme yetkisi yok. Yöneticinize başvurun.', 403)
            : session.error
        }
        onRetry={forbidden ? undefined : () => void session.refetch()}
      >
        <Button title="Çıkış yap" variant="danger" onPress={() => void signOut()} />
      </ErrorState>
    );
  }

  const confirmClose = () => {
    Alert.alert('Oturumu kapat', 'Aktif saha oturumu kapatılsın mı?', [
      { text: 'Vazgeç', style: 'cancel' },
      { text: 'Kapat', style: 'destructive', onPress: () => closeSession.mutate() },
    ]);
  };

  return (
    <ScrollView contentContainerStyle={styles.content}>
      <Text style={styles.greeting}>Merhaba, {user.name}</Text>

      {closeSession.isError ? <ErrorBanner message={errorMessage(closeSession.error)} /> : null}

      {session.data ? (
        <>
          <SessionCard session={session.data} />
          <Button title="Plaka tara" onPress={() => router.push('/scan')} />
          <Button title="Oturumu değiştir" variant="secondary" onPress={() => router.push('/session')} />
          <Button title="Oturumu kapat" variant="danger" onPress={confirmClose} loading={closeSession.isPending} />
        </>
      ) : (
        <>
          <View style={styles.card}>
            <Text style={styles.cardTitle}>Aktif oturum yok</Text>
            <Text style={styles.hint}>Fiş kesmek için şantiye, döküm yeri ve vardiya seçin.</Text>
          </View>
          <Button title="Oturum başlat" onPress={() => router.push('/session')} />
        </>
      )}

      <View style={styles.footer}>
        <Button title="Çıkış yap" variant="secondary" onPress={() => void signOut()} />
      </View>
    </ScrollView>
  );
}

function SessionCard({ session }: { session: FieldSession }) {
  const lockedAt = new Date(session.locked_at).toLocaleString('tr-TR', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });

  return (
    <View style={styles.card}>
      <Text style={styles.cardTitle}>Aktif oturum</Text>
      <InfoRow label="Şantiye" value={session.site_name ?? '-'} />
      <InfoRow label="Döküm yeri" value={session.dump_site_name ?? '-'} />
      <InfoRow label="Vardiya" value={session.shift_label} />
      <InfoRow label="Başlangıç" value={lockedAt} />
    </View>
  );
}

const styles = StyleSheet.create({
  content: {
    gap: spacing.md,
    padding: spacing.lg,
  },
  greeting: {
    fontSize: fontSize.title,
    fontWeight: '700',
    color: colors.text,
  },
  card: {
    gap: spacing.sm,
    padding: spacing.md,
    borderRadius: radius,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: colors.surface,
  },
  cardTitle: {
    fontSize: fontSize.body,
    fontWeight: '700',
    color: colors.text,
  },
  hint: {
    fontSize: fontSize.small,
    color: colors.muted,
  },
  footer: {
    marginTop: spacing.lg,
  },
});
