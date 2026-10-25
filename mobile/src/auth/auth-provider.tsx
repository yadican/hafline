import { useQueryClient } from '@tanstack/react-query';
import { createContext, use, useEffect, useMemo, useState, type ReactNode } from 'react';

import { createApiClient, normalizeServerUrl, type ApiClient } from '@/api/client';
import { login, logout } from '@/api/endpoints';
import type { User } from '@/api/types';

import { clearSignIn, readStoredSession, storeSignIn } from './storage';

type AuthState =
  | { status: 'loading' }
  | { status: 'signedOut'; serverUrl: string | null }
  | { status: 'signedIn'; serverUrl: string; token: string; user: User };

type AuthContextValue = {
  state: AuthState;
  client: ApiClient | null;
  signIn: (serverUrl: string, loginName: string, password: string) => Promise<void>;
  signOut: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const queryClient = useQueryClient();
  const [state, setState] = useState<AuthState>({ status: 'loading' });

  useEffect(() => {
    readStoredSession()
      .then(({ serverUrl, token, user }) => {
        if (serverUrl && token && user) {
          setState({ status: 'signedIn', serverUrl, token, user });
        } else {
          setState({ status: 'signedOut', serverUrl });
        }
      })
      .catch(() => setState({ status: 'signedOut', serverUrl: null }));
  }, []);

  const value = useMemo<AuthContextValue>(() => {
    const dropSession = async (serverUrl: string | null) => {
      await clearSignIn();
      queryClient.clear();
      setState({ status: 'signedOut', serverUrl });
    };

    const client =
      state.status === 'signedIn'
        ? createApiClient({
            baseUrl: state.serverUrl,
            token: state.token,
            onUnauthorized: () => void dropSession(state.serverUrl),
          })
        : null;

    return {
      state,
      client,
      async signIn(serverUrlInput, loginName, password) {
        const serverUrl = normalizeServerUrl(serverUrlInput);
        const response = await login(createApiClient({ baseUrl: serverUrl }), {
          login: loginName.trim(),
          password,
        });

        await storeSignIn(serverUrl, response.token, response.user);
        setState({ status: 'signedIn', serverUrl, token: response.token, user: response.user });
      },
      async signOut() {
        if (state.status !== 'signedIn' || !client) {
          return;
        }

        await logout(client).catch(() => undefined);
        await dropSession(state.serverUrl);
      },
    };
  }, [queryClient, state]);

  return <AuthContext value={value}>{children}</AuthContext>;
}

export function useAuth(): AuthContextValue {
  const context = use(AuthContext);

  if (!context) {
    throw new Error('useAuth must be used inside AuthProvider');
  }

  return context;
}

export function useSignedInUser(): User {
  const { state } = useAuth();

  if (state.status !== 'signedIn') {
    throw new Error('useSignedInUser requires a signed-in user');
  }

  return state.user;
}

export function useApiClient(): ApiClient {
  const { client } = useAuth();

  if (!client) {
    throw new Error('useApiClient requires a signed-in user');
  }

  return client;
}
