import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { createHaul, fetchHaul, scanPlate } from '@/api/endpoints';
import type { HaulStoreRequest, HaulTicket } from '@/api/types';
import { useApiClient } from '@/auth/auth-provider';

const keys = {
  ticket: (haulId: string) => ['haul', haulId] as const,
};

export function useScanPlate() {
  const client = useApiClient();

  return useMutation({
    mutationFn: ({ fieldSessionId, photoUri }: { fieldSessionId: string; photoUri: string }) =>
      scanPlate(client, fieldSessionId, photoUri),
  });
}

export function useCreateHaul() {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: HaulStoreRequest) => createHaul(client, body),
    onSuccess: (ticket: HaulTicket) => queryClient.setQueryData(keys.ticket(ticket.id), ticket),
  });
}

export function useHaulTicket(haulId: string) {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.ticket(haulId),
    queryFn: () => fetchHaul(client, haulId),
    staleTime: Infinity,
  });
}
