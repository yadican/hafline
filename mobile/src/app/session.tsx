import { router, useLocalSearchParams } from 'expo-router';
import { ScrollView, StyleSheet } from 'react-native';

import { ErrorState, LoadingState } from '@/components/screen-state';
import { useCurrentSession } from '@/features/field-session/queries';
import { SessionForm } from '@/features/field-session/session-form';
import { spacing } from '@/theme';

export default function SessionScreen() {
  const { scope } = useLocalSearchParams<{ scope?: 'haul' }>();
  const current = useCurrentSession();

  if (current.isPending) {
    return <LoadingState />;
  }

  if (current.isError) {
    return <ErrorState error={current.error} onRetry={() => void current.refetch()} />;
  }

  return (
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      <SessionForm
        initial={current.data}
        askDumpSite={scope === 'haul'}
        submitLabel="Kaydet"
        onSaved={() => router.back()}
      />
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  content: {
    padding: spacing.lg,
  },
});
