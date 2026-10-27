import * as SecureStore from 'expo-secure-store';

import type { User } from '@/api/types';

const KEYS = {
  serverUrl: 'hafline.server_url',
  token: 'hafline.token',
  user: 'hafline.user',
} as const;

export type StoredSession = {
  serverUrl: string | null;
  token: string | null;
  user: User | null;
};

export async function readStoredSession(): Promise<StoredSession> {
  const [serverUrl, token, userJson] = await Promise.all([
    SecureStore.getItemAsync(KEYS.serverUrl),
    SecureStore.getItemAsync(KEYS.token),
    SecureStore.getItemAsync(KEYS.user),
  ]);

  return {
    serverUrl,
    token,
    user: parseUser(userJson),
  };
}

export async function storeSignIn(serverUrl: string, token: string, user: User): Promise<void> {
  await Promise.all([
    SecureStore.setItemAsync(KEYS.serverUrl, serverUrl),
    SecureStore.setItemAsync(KEYS.token, token),
    SecureStore.setItemAsync(KEYS.user, JSON.stringify(user)),
  ]);
}

export async function clearSignIn(): Promise<void> {
  await Promise.all([
    SecureStore.deleteItemAsync(KEYS.token),
    SecureStore.deleteItemAsync(KEYS.user),
  ]);
}

function parseUser(json: string | null): User | null {
  if (!json) {
    return null;
  }

  try {
    return JSON.parse(json) as User;
  } catch {
    return null;
  }
}
