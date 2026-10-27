import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  checkHaulPlate,
  createHaul,
  deleteHaul,
  fetchHaul,
  fetchHaulSession,
  scanHaulPlate,
  searchHaulVehicles,
  updateHaul,
} from '@/api/endpoints';
import type { HaulStoreRequest, HaulTicket, HaulUpdateRequest } from '@/api/types';
import { useApiClient } from '@/auth/auth-provider';
import { MIN_PLATE_QUERY } from '@/features/plate/plate';

const keys = {
  session: ['haul', 'session'] as const,
  search: (query: string) => ['haul', 'search', query] as const,
  check: (plate: string) => ['haul', 'check', plate] as const,
  ticket: (haulId: string) => ['haul', 'ticket', haulId] as const,
};

export function useHaulVehicleSearch(query: string) {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.search(query),
    queryFn: () => searchHaulVehicles(client, query),
    enabled: query.length >= MIN_PLATE_QUERY,
    placeholderData: keepPreviousData,
  });
}

export function useHaulPlateCheck(plate: string) {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.check(plate),
    queryFn: () => checkHaulPlate(client, plate),
    enabled: plate.length >= MIN_PLATE_QUERY,
  });
}

export function useScanHaulPlate() {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ fieldSessionId, photoUri }: { fieldSessionId: string; photoUri: string }) =>
      scanHaulPlate(client, fieldSessionId, photoUri),
    onSuccess: (scan) => {
      if (scan.vehicle.plate) {
        queryClient.setQueryData(keys.check(scan.vehicle.plate), scan);
      }
    },
  });
}

export function useHaulSession() {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.session,
    queryFn: () => fetchHaulSession(client),
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

export function useCreateHaul() {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: HaulStoreRequest) => createHaul(client, body),
    onSuccess: (ticket: HaulTicket) => {
      queryClient.setQueryData(keys.ticket(ticket.id), ticket);
      void queryClient.invalidateQueries({ queryKey: keys.session });
    },
  });
}

export function useUpdateHaul(haulId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: HaulUpdateRequest) => updateHaul(client, haulId, body),
    onSuccess: (ticket: HaulTicket) => {
      queryClient.setQueryData(keys.ticket(ticket.id), ticket);
      void queryClient.invalidateQueries({ queryKey: keys.session });
    },
  });
}

export function useDeleteHaul(haulId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => deleteHaul(client, haulId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: keys.session }),
  });
}
