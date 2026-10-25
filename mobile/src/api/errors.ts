export const NETWORK_ERROR_MESSAGE = 'Sunucuya ulaşılamadı. Sunucu adresini ve internet bağlantısını kontrol edin.';
export const UNEXPECTED_ERROR_MESSAGE = 'Beklenmeyen bir hata oluştu.';

export class ApiError extends Error {
  constructor(
    message: string,
    readonly status: number,
    readonly fieldErrors: Record<string, string[]> = {},
  ) {
    super(message);
    this.name = 'ApiError';
  }

  get isClientError(): boolean {
    return this.status >= 400 && this.status < 500;
  }

  firstFieldError(field: string): string | undefined {
    return this.fieldErrors[field]?.[0];
  }
}

const STATUS_MESSAGES: Record<number, string> = {
  401: 'Oturumun süresi doldu. Lütfen tekrar giriş yapın.',
  403: 'Bu işlem için yetkiniz yok.',
  404: 'Kayıt bulunamadı.',
  429: 'Çok fazla deneme yapıldı. Biraz bekleyip tekrar deneyin.',
  502: 'Plaka okuma servisine ulaşılamadı.',
};

export function toApiError(status: number, body: unknown): ApiError {
  const payload = (typeof body === 'object' && body !== null ? body : {}) as {
    message?: unknown;
    errors?: unknown;
  };

  const fieldErrors =
    typeof payload.errors === 'object' && payload.errors !== null
      ? (payload.errors as Record<string, string[]>)
      : {};

  const firstFieldMessage = Object.values(fieldErrors)[0]?.[0];
  const serverMessage = typeof payload.message === 'string' ? payload.message : undefined;

  const message =
    firstFieldMessage ??
    STATUS_MESSAGES[status] ??
    serverMessage ??
    `Beklenmeyen bir hata oluştu (${status}).`;

  return new ApiError(message, status, fieldErrors);
}

export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    return error.message;
  }

  return UNEXPECTED_ERROR_MESSAGE;
}
