import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  closeSession,
  fetchCurrentSession,
  fetchDumpSites,
  fetchHaulOptions,
  lockSession,
} from '@/api/endpoints';
import type { FieldSession, Shift } from '@/api/types';
import { useApiClient } from '@/auth/auth-provider';

const keys = {
  current: ['field-session', 'current'] as const,
  options: ['field-session', 'options'] as const,
  dumpSites: (siteId: string) => ['field-session', 'dump-sites', siteId] as const,
};

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
    mutationFn: (body: { site_id: string; dump_site_id: string; shift: Shift }) => lockSession(client, body),
    onSuccess: (session: FieldSession) => queryClient.setQueryData(keys.current, session),
  });
}

export function useCloseSession() {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => closeSession(client),
    onSuccess: () => queryClient.setQueryData(keys.current, null),
  });
}
