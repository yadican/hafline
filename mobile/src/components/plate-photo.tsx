import { Image } from 'expo-image';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { errorMessage } from '@/api/errors';
import { PhotoPermissionError, pickPlatePhoto, type PhotoSource } from '@/features/plate/photo';
import { colors, fontSize, fonts, radius, spacing } from '@/theme';

import { ActionTile, ActionTileRow } from './action-tile';
import { Button } from './button';
import { icons } from './icons';
import { ErrorBanner } from './screen-state';

type PlatePhotoProps = {
  photoUri: string | null;
  hint: string;
  scanning: boolean;
  scanError: unknown;
  onPhoto: (uri: string) => void;
  onRetry: () => void;
  /** Hides the camera/gallery buttons, e.g. while the ticket is being saved. */
  busy?: boolean;
};

export function PlatePhoto({ photoUri, hint, scanning, scanError, onPhoto, onRetry, busy = false }: PlatePhotoProps) {
  const [pickError, setPickError] = useState<string | null>(null);

  const choosePhoto = async (source: PhotoSource) => {
    setPickError(null);

    try {
      const uri = await pickPlatePhoto(source);

      if (uri) {
        onPhoto(uri);
      }
    } catch (error) {
      setPickError(error instanceof PhotoPermissionError ? error.message : 'Fotoğraf alınamadı. Tekrar deneyin.');
    }
  };

  return (
    <View style={styles.container}>
      {pickError ? <ErrorBanner message={pickError} /> : null}

      {photoUri ? (
        <Image source={{ uri: photoUri }} style={styles.photo} contentFit="contain" />
      ) : (
        <View style={styles.card}>
          <Text style={styles.cardTitle}>Plaka fotoğrafı</Text>
          <Text style={styles.hint}>{hint}</Text>
        </View>
      )}

      {scanning ? (
        <View style={styles.reading}>
          <ActivityIndicator color={colors.primary} />
          <Text style={styles.readingText}>Plaka okunuyor…</Text>
        </View>
      ) : null}

      {scanError && photoUri && !scanning ? (
        <>
          <ErrorBanner message={errorMessage(scanError)} />
          <Button title="Tekrar dene" variant="secondary" icon={icons.retry} onPress={onRetry} />
        </>
      ) : null}

      {!scanning && !busy ? (
        <ActionTileRow>
          <ActionTile
            title={photoUri ? 'Yeni fotoğraf' : 'Fotoğraf çek'}
            icon={icons.camera}
            onPress={() => void choosePhoto('camera')}
          />
          <ActionTile title="Galeriden seç" icon={icons.gallery} onPress={() => void choosePhoto('library')} />
        </ActionTileRow>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    gap: spacing.md,
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
    fontFamily: fonts.bold,
    color: colors.text,
  },
  hint: {
    fontSize: fontSize.small,
    fontFamily: fonts.medium,
    color: colors.muted,
  },
  photo: {
    width: '100%',
    height: 220,
    borderRadius: radius,
    backgroundColor: colors.steel,
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
    fontFamily: fonts.semibold,
    color: colors.text,
  },
});
