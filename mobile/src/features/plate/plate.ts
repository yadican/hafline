/** The search and check-plate endpoints ignore anything shorter. */
export const MIN_PLATE_QUERY = 2;

export const PLATE_MAX_LENGTH = 20;

export function normalizePlateInput(text: string): string {
  return text.toUpperCase();
}

/** OCR confidence as a whole percentage; accepts both 0–1 and 0–100 scales. */
export function confidencePercent(confidence: number | null): number | null {
  if (confidence === null) {
    return null;
  }

  return Math.round(confidence <= 1 ? confidence * 100 : confidence);
}

export function scanReadLine(scan: { plate: string | null; confidence: number | null }): string {
  const confidence = confidencePercent(scan.confidence);

  return `${scan.plate ? `Okunan: ${scan.plate}` : 'Plaka okunamadı.'}${confidence !== null ? `  ·  Güven: %${confidence}` : ''}`;
}
