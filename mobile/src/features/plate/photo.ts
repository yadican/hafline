import { ImageManipulator, SaveFormat } from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';

/** Keeps plate glyphs sharp for OCR while staying well under the server's 15 MB / 4096 px limits. */
const MAX_EDGE = 2048;
const JPEG_QUALITY = 0.85;

export type PhotoSource = 'camera' | 'library';

export class PhotoPermissionError extends Error {
  constructor(source: PhotoSource) {
    super(
      source === 'camera'
        ? 'Kamera izni verilmedi. Telefon ayarlarından bu uygulamaya kamera izni verin.'
        : 'Galeri izni verilmedi. Telefon ayarlarından bu uygulamaya fotoğraf izni verin.',
    );
    this.name = 'PhotoPermissionError';
  }
}

/**
 * Opens the camera or gallery and returns a downscaled JPEG ready for upload, or null when cancelled.
 */
export async function pickPlatePhoto(source: PhotoSource): Promise<string | null> {
  const permission =
    source === 'camera'
      ? await ImagePicker.requestCameraPermissionsAsync()
      : await ImagePicker.requestMediaLibraryPermissionsAsync();

  if (!permission.granted) {
    throw new PhotoPermissionError(source);
  }

  const options: ImagePicker.ImagePickerOptions = { mediaTypes: ['images'], quality: 1 };
  const result =
    source === 'camera'
      ? await ImagePicker.launchCameraAsync(options)
      : await ImagePicker.launchImageLibraryAsync(options);

  if (result.canceled) {
    return null;
  }

  return prepareForUpload(result.assets[0].uri);
}

async function prepareForUpload(uri: string): Promise<string> {
  const image = await ImageManipulator.manipulate(uri, { maxWidth: MAX_EDGE, maxHeight: MAX_EDGE }).renderAsync();
  const saved = await image.saveAsync({ compress: JPEG_QUALITY, format: SaveFormat.JPEG });

  return saved.uri;
}
