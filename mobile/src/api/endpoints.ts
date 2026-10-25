import { File } from 'expo-file-system';

import { unwrap, type ApiClient } from './client';
import type {
  FieldSession,
  FieldSessionRequest,
  HaulOptions,
  HaulStoreRequest,
  HaulTicket,
  LoginRequest,
  LoginResponse,
  NamedOption,
  PlateScan,
} from './types';

export function login(client: ApiClient, body: LoginRequest): Promise<LoginResponse> {
  return unwrap(client.POST('/api/v1/auth/login', { body }));
}

export async function logout(client: ApiClient): Promise<void> {
  await unwrap(client.POST('/api/v1/auth/logout'));
}

export function fetchHaulOptions(client: ApiClient): Promise<HaulOptions> {
  return unwrap(client.GET('/api/v1/hauls/options'));
}

export async function fetchDumpSites(client: ApiClient, siteId: string): Promise<NamedOption[]> {
  const result = await unwrap(client.GET('/api/v1/hauls/dump-sites', { params: { query: { site_id: siteId } } }));

  return result.dump_sites;
}

export async function fetchCurrentSession(client: ApiClient): Promise<FieldSession | null> {
  const result = await unwrap(client.GET('/api/v1/field-sessions/current'));

  return result.data ?? null;
}

export async function lockSession(client: ApiClient, body: FieldSessionRequest): Promise<FieldSession> {
  const result = await unwrap(client.POST('/api/v1/field-sessions', { body }));

  return result.data;
}

export async function closeSession(client: ApiClient): Promise<void> {
  await unwrap(client.DELETE('/api/v1/field-sessions/current'));
}

export function scanPlate(client: ApiClient, fieldSessionId: string, photoUri: string): Promise<PlateScan> {
  const form = new FormData();
  // Expo's fetch rejects React Native's { uri, name, type } descriptors; parts must be Blob-like.
  form.append('file', new File(photoUri));
  form.append('field_session_id', fieldSessionId);

  return unwrap(
    client.POST('/api/v1/hauls/scan', {
      body: form as unknown as { file: string; field_session_id: string },
      bodySerializer: () => form,
    }),
  );
}

export async function createHaul(client: ApiClient, body: HaulStoreRequest): Promise<HaulTicket> {
  const result = await unwrap(client.POST('/api/v1/hauls', { body }));

  return result.data;
}

export async function fetchHaul(client: ApiClient, haulId: string): Promise<HaulTicket> {
  const result = await unwrap(client.GET('/api/v1/hauls/{haul}', { params: { path: { haul: haulId } } }));

  return result.data;
}
