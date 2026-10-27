import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import {
  checkFuelPlate,
  createFuelReceipt,
  deleteFuelReceipt,
  fetchFuelReceipt,
  fetchFuelReceiptOptions,
  fetchFuelReceiptSession,
  scanFuelPlate,
  searchFuelVehicles,
  updateFuelReceipt,
} from '@/api/endpoints';
import type { FuelReceiptStoreRequest, FuelReceiptTicket, FuelReceiptUpdateRequest } from '@/api/types';
import { useApiClient } from '@/auth/auth-provider';
import { MIN_PLATE_QUERY } from '@/features/plate/plate';

const keys = {
  session: ['fuel-receipt', 'session'] as const,
  options: ['fuel-receipt', 'options'] as const,
  search: (query: string) => ['fuel-receipt', 'search', query] as const,
  check: (plate: string) => ['fuel-receipt', 'check', plate] as const,
  ticket: (receiptId: string) => ['fuel-receipt', 'ticket', receiptId] as const,
};

export function useFuelReceiptOptions() {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.options,
    queryFn: () => fetchFuelReceiptOptions(client),
  });
}

export function useFuelVehicleSearch(query: string) {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.search(query),
    queryFn: () => searchFuelVehicles(client, query),
    enabled: query.length >= MIN_PLATE_QUERY,
    placeholderData: keepPreviousData,
  });
}

export function useFuelPlateCheck(plate: string) {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.check(plate),
    queryFn: () => checkFuelPlate(client, plate),
    enabled: plate.length >= MIN_PLATE_QUERY,
  });
}

export function useScanFuelPlate() {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: ({ fieldSessionId, photoUri }: { fieldSessionId: string; photoUri: string }) =>
      scanFuelPlate(client, fieldSessionId, photoUri),
    onSuccess: (scan) => {
      if (scan.vehicle.plate) {
        queryClient.setQueryData(keys.check(scan.vehicle.plate), scan);
      }
    },
  });
}

export function useFuelReceiptSession() {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.session,
    queryFn: () => fetchFuelReceiptSession(client),
  });
}

export function useFuelReceiptTicket(receiptId: string) {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.ticket(receiptId),
    queryFn: () => fetchFuelReceipt(client, receiptId),
    staleTime: Infinity,
  });
}

export function useCreateFuelReceipt() {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: FuelReceiptStoreRequest) => createFuelReceipt(client, body),
    onSuccess: (ticket: FuelReceiptTicket) => {
      queryClient.setQueryData(keys.ticket(ticket.id), ticket);
      void queryClient.invalidateQueries({ queryKey: keys.session });
    },
  });
}

export function useUpdateFuelReceipt(receiptId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: (body: FuelReceiptUpdateRequest) => updateFuelReceipt(client, receiptId, body),
    onSuccess: (ticket: FuelReceiptTicket) => {
      queryClient.setQueryData(keys.ticket(ticket.id), ticket);
      void queryClient.invalidateQueries({ queryKey: keys.session });
    },
  });
}

export function useDeleteFuelReceipt(receiptId: string) {
  const client = useApiClient();
  const queryClient = useQueryClient();

  return useMutation({
    mutationFn: () => deleteFuelReceipt(client, receiptId),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: keys.session }),
  });
}
