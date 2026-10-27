import createClient, { type Client } from 'openapi-fetch';

import { ApiError, NETWORK_ERROR_MESSAGE, UNEXPECTED_ERROR_MESSAGE, toApiError } from './errors';
import type { paths } from './schema';

export type ApiClient = Client<paths>;

type ClientOptions = {
  baseUrl: string;
  token?: string;
  onUnauthorized?: () => void;
};

export function createApiClient({ baseUrl, token, onUnauthorized }: ClientOptions): ApiClient {
  const client = createClient<paths>({
    baseUrl,
    headers: { Accept: 'application/json' },
  });

  // Hooks must not return the request/response: openapi-fetch checks `instanceof Request/Response`,
  // and React Native's fetch returns objects that fail that check.
  client.use({
    onRequest({ request }) {
      if (token) {
        request.headers.set('Authorization', `Bearer ${token}`);
      }
    },
    onResponse({ response }) {
      if (response.status === 401 && token) {
        onUnauthorized?.();
      }
    },
  });

  return client;
}

type FetchResult<T> = {
  data?: T;
  error?: unknown;
  response: Response;
};

/** Resolves with the typed body of a successful response; rejects with an ApiError otherwise. */
export async function unwrap<T>(request: Promise<FetchResult<T>>): Promise<T> {
  let result: FetchResult<T>;

  try {
    result = await request;
  } catch (error) {
    if (__DEV__) {
      console.warn('API request failed', error);
    }

    // fetch rejects with a TypeError only when the server could not be reached.
    if (error instanceof TypeError) {
      throw new ApiError(NETWORK_ERROR_MESSAGE, 0);
    }

    throw new ApiError(UNEXPECTED_ERROR_MESSAGE, 0);
  }

  if (!result.response.ok) {
    throw toApiError(result.response.status, result.error);
  }

  return result.data as T;
}

export function normalizeServerUrl(input: string): string {
  const trimmed = input.trim().replace(/\/+$/, '');

  if (trimmed === '') {
    return '';
  }

  return /^https?:\/\//i.test(trimmed) ? trimmed : `http://${trimmed}`;
}
