import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  checkMachinePlate,
  createMachineWork,
  deleteMachineWork,
  fetchMachineWork,
  fetchMachineWorkOptions,
  fetchMachineWorkSession,
  scanMachinePlate,
  searchMachines,
  updateMachineWork,
} from '@/api/endpoints';
import type { MachineWorkStoreRequest, MachineWorkTicket, MachineWorkUpdateRequest } from '@/api/types';
import { useApiClient } from '@/auth/auth-provider';
import { MIN_PLATE_QUERY } from '@/features/plate/plate';

const keys = {
  session: ['machine-work', 'session'] as const,
  options: ['machine-work', 'options'] as const,
  search: (query: string) => ['machine-work', 'search', query] as const,
  check: (plate: string) => ['machine-work', 'check', plate] as const,
  ticket: (workId: string) => ['machine-work', 'ticket', workId] as const,
};

export function useMachineWorkOptions() {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.options,
    queryFn: () => fetchMachineWorkOptions(client),
    staleTime: 5 * 60 * 1000,
  });
}

export function useMachineSearch(query: string) {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.search(query),
    queryFn: () => searchMachines(client, query),
    enabled: query.length >= MIN_PLATE_QUERY,
    placeholderData: keepPreviousData,
  });
}

export function useMachinePlateCheck(plate: string) {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.check(plate),
    queryFn: () => checkMachinePlate(client, plate),
    enabled: plate.length >= MIN_PLATE_QUERY,
  });
}

export function useScanMachinePlate() {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ fieldSessionId, photoUri }: { fieldSessionId: string; photoUri: string }) =>
      scanMachinePlate(client, fieldSessionId, photoUri),
    onSuccess: (scan) => {
      if (scan.vehicle.plate) {
        queryClient.setQueryData(keys.check(scan.vehicle.plate), scan);
      }
    },
  });
}

export function useMachineWorkSession() {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.session,
    queryFn: () => fetchMachineWorkSession(client),
  });
}

export function useMachineWorkTicket(workId: string) {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.ticket(workId),
    queryFn: () => fetchMachineWork(client, workId),
    staleTime: Infinity,
  });
}

export function useCreateMachineWork() {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: MachineWorkStoreRequest) => createMachineWork(client, body),
    onSuccess: (ticket: MachineWorkTicket) => {
      queryClient.setQueryData(keys.ticket(ticket.id), ticket);
      void queryClient.invalidateQueries({ queryKey: keys.session });
      void queryClient.invalidateQueries({ queryKey: keys.options });
    },
  });
}

export function useUpdateMachineWork(workId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: MachineWorkUpdateRequest) => updateMachineWork(client, workId, body),
    onSuccess: (ticket: MachineWorkTicket) => {
      queryClient.setQueryData(keys.ticket(ticket.id), ticket);
      void queryClient.invalidateQueries({ queryKey: keys.session });
      void queryClient.invalidateQueries({ queryKey: keys.options });
    },
  });
}

export function useDeleteMachineWork(workId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => deleteMachineWork(client, workId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: keys.session }),
  });
}
