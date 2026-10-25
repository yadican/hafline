import { Image } from 'expo-image';
import { router } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, StyleSheet, Text, View } from 'react-native';

import { ApiError, errorMessage } from '@/api/errors';
import type { PlateScan } from '@/api/types';
import { Button } from '@/components/button';
import { ErrorBanner, ErrorState, LoadingState } from '@/components/screen-state';
import { TextField } from '@/components/text-field';
import { useCurrentSession } from '@/features/field-session/queries';
import { PhotoPermissionError, pickPlatePhoto, type PhotoSource } from '@/features/haul/photo';
import { useCreateHaul, useScanPlate } from '@/features/haul/queries';
import { colors, fontSize, radius, spacing } from '@/theme';

export default function ScanScreen() {
  const session = useCurrentSession();
  const scanPlate = useScanPlate();
  const createHaul = useCreateHaul();

  const [photoUri, setPhotoUri] = useState<string | null>(null);
  const [scan, setScan] = useState<PlateScan | null>(null);
  const [plate, setPlate] = useState('');
  const [pickError, setPickError] = useState<string | null>(null);

  if (session.isPending) {
    return <LoadingState />;
  }

  if (session.isError) {
    return <ErrorState error={session.error} onRetry={() => void session.refetch()} />;
  }

  if (!session.data) {
    return <ErrorState error={new ApiError('Aktif saha oturumu yok. Önce oturum başlatın.', 0)} />;
  }

  const fieldSessionId = session.data.id;

  const startScan = (uri: string) => {
    setPhotoUri(uri);
    setScan(null);
    setPlate('');
    createHaul.reset();
    scanPlate.mutate(
      { fieldSessionId, photoUri: uri },
      {
        onSuccess: (result) => {
          setScan(result);
          setPlate(result.vehicle.plate ?? result.plate ?? '');
        },
      },
    );
  };

  const choosePhoto = async (source: PhotoSource) => {
    setPickError(null);

    try {
      const uri = await pickPlatePhoto(source);

      if (uri) {
        startScan(uri);
      }
    } catch (error) {
      setPickError(error instanceof PhotoPermissionError ? error.message : 'Fotoğraf alınamadı. Tekrar deneyin.');
    }
  };

  const save = () => {
    if (!scan) {
      return;
    }

    createHaul.mutate(
      { plate: plate.trim(), scan_id: scan.scan_id, field_session_id: fieldSessionId },
      { onSuccess: (ticket) => router.replace({ pathname: '/ticket/[id]', params: { id: ticket.id } }) },
    );
  };

  const saveError =
    createHaul.error instanceof ApiError
      ? (createHaul.error.firstFieldError('plate') ?? createHaul.error.message)
      : createHaul.isError
        ? errorMessage(createHaul.error)
        : null;

  return (
    <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
      {pickError ? <ErrorBanner message={pickError} /> : null}

      {photoUri ? (
        <Image source={{ uri: photoUri }} style={styles.photo} contentFit="contain" />
      ) : (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Plaka fotoğrafı</Text>
          <Text style={styles.hint}>
            Plaka okunaklı ve kadrajın ortasında olacak şekilde fotoğraf çekin ya da galeriden seçin.
          </Text>
        </View>
      )}

      {scanPlate.isPending ? (
        <View style={styles.reading}>
          <ActivityIndicator color={colors.primary} />
          <Text style={styles.readingText}>Plaka okunuyor…</Text>
        </View>
      ) : null}

      {scanPlate.isError && photoUri ? (
        <>
          <ErrorBanner message={errorMessage(scanPlate.error)} />
          <Button title="Tekrar dene" onPress={() => startScan(photoUri)} />
        </>
      ) : null}

      {scan ? (
        <>
          <ScanStatus scan={scan} />
          <TextField
            label="Plaka"
            value={plate}
            onChangeText={(text) => setPlate(text.toUpperCase())}
            placeholder="34 ABC 123"
            autoCapitalize="characters"
            autoCorrect={false}
            maxLength={20}
          />
          {saveError ? <ErrorBanner message={saveError} /> : null}
          <Button
            title="Fişi kaydet"
            onPress={save}
            loading={createHaul.isPending}
            disabled={plate.trim() === ''}
          />
        </>
      ) : null}

      {!scanPlate.isPending && !createHaul.isPending ? (
        <View style={styles.sources}>
          <Button
            title={photoUri ? 'Yeni fotoğraf çek' : 'Fotoğraf çek'}
            variant={photoUri ? 'secondary' : 'primary'}
            onPress={() => void choosePhoto('camera')}
          />
          <Button title="Galeriden seç" variant="secondary" onPress={() => void choosePhoto('library')} />
        </View>
      ) : null}
    </ScrollView>
  );
}

function ScanStatus({ scan }: { scan: PlateScan }) {
  const ready = scan.can_create;
  const confidence =
    scan.confidence === null ? null : Math.round(scan.confidence <= 1 ? scan.confidence * 100 : scan.confidence);

  return (
    <View style={[styles.status, ready ? styles.statusReady : styles.statusWarning]}>
      <Text style={[styles.statusTitle, { color: ready ? colors.success : colors.warning }]}>
        {ready ? `Kayıtlı araç: ${scan.vehicle.plate}` : (scan.message ?? 'Plakayı kontrol edin.')}
      </Text>
      <Text style={styles.statusDetail}>
        {scan.plate ? `Okunan: ${scan.plate}` : 'Plaka okunamadı.'}
        {confidence !== null ? `  ·  Güven: %${confidence}` : ''}
      </Text>
      {!ready ? <Text style={styles.statusDetail}>Plakayı aşağıdan düzeltip kaydedebilirsiniz.</Text> : null}
    </View>
  );
}

const styles = StyleSheet.create({
  content: {
    gap: spacing.md,
    padding: spacing.lg,
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
  photo: {
    width: '100%',
    height: 220,
    borderRadius: radius,
    backgroundColor: colors.text,
  },
  reading: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    gap: spacing.sm,
    padding: spacing.md,
  },
  readingText: {
    fontSize: fontSize.body,
    color: colors.text,
  },
  status: {
    gap: spacing.xs,
    padding: spacing.md,
    borderRadius: radius,
    borderWidth: 1,
  },
  statusReady: {
    borderColor: colors.success,
    backgroundColor: colors.successSurface,
  },
  statusWarning: {
    borderColor: colors.warning,
    backgroundColor: colors.warningSurface,
  },
  statusTitle: {
    fontSize: fontSize.body,
    fontWeight: '700',
  },
  statusDetail: {
    fontSize: fontSize.small,
    color: colors.text,
  },
  sources: {
    gap: spacing.sm,
  },
});
