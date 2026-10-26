import { useMutation, useQuery, useQueryClient, type QueryClient } from '@tanstack/react-query';

import { fetchCurrentSession, fetchDumpSites, fetchHaulOptions, lockSession } from '@/api/endpoints';
import type { FieldSession, FieldSessionRequest } from '@/api/types';
import { useApiClient } from '@/auth/auth-provider';

const keys = {
  current: ['field-session', 'current'] as const,
  options: ['field-session', 'options'] as const,
  dumpSites: (siteId: string) => ['field-session', 'dump-sites', siteId] as const,
};

/** Ticket lists and fuel stations depend on the session's site and shift. */
const SESSION_SCOPED_ROOTS = ['haul', 'machine-work', 'fuel-receipt'] as const;

function invalidateSessionScoped(queryClient: QueryClient) {
  for (const root of SESSION_SCOPED_ROOTS) {
    void queryClient.invalidateQueries({ queryKey: [root] });
  }
}

export function useCurrentSession() {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.current,
    queryFn: () => fetchCurrentSession(client),
  });
}

export function useHaulOptions() {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.options,
    queryFn: () => fetchHaulOptions(client),
    staleTime: 5 * 60 * 1000,
  });
}

export function useDumpSites(siteId: string | null) {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.dumpSites(siteId ?? ''),
    queryFn: () => fetchDumpSites(client, siteId ?? ''),
    enabled: siteId !== null,
    staleTime: 5 * 60 * 1000,
  });
}

export function useLockSession() {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: FieldSessionRequest) => lockSession(client, body),
    onSuccess: (session: FieldSession) => {
      queryClient.setQueryData(keys.current, session);
      invalidateSessionScoped(queryClient);
    },
  });
}
