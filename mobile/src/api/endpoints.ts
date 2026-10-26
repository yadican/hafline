import { File } from 'expo-file-system';

import { unwrap, type ApiClient } from './client';
import type {
  FieldSession,
  FieldSessionRequest,
  FuelPlateCheck,
  FuelPlateScan,
  FuelReceiptOptions,
  FuelReceiptStoreRequest,
  FuelReceiptTicket,
  FuelReceiptUpdateRequest,
  FuelVehicleSearchResult,
  HaulOptions,
  HaulPlateCheck,
  HaulStoreRequest,
  HaulTicket,
  HaulUpdateRequest,
  HaulVehicleSearchResult,
  LoginRequest,
  LoginResponse,
  MachinePlateCheck,
  MachinePlateScan,
  MachineSearchResult,
  MachineWorkOptions,
  MachineWorkStoreRequest,
  MachineWorkTicket,
  MachineWorkUpdateRequest,
  NamedOption,
  PlateScan,
  SessionFuelReceipts,
  SessionHauls,
  SessionMachineWorks,
} from './types';

type ScanBody = { file: string; field_session_id: string };

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

function scanForm(fieldSessionId: string, photoUri: string): FormData {
  const form = new FormData();
  // Expo's fetch rejects React Native's { uri, name, type } descriptors; parts must be Blob-like.
  form.append('file', new File(photoUri));
  form.append('field_session_id', fieldSessionId);

  return form;
}

export async function searchHaulVehicles(client: ApiClient, query: string): Promise<HaulVehicleSearchResult[]> {
  const result = await unwrap(client.GET('/api/v1/hauls/vehicles', { params: { query: { q: query } } }));

  return result.vehicles;
}

export function checkHaulPlate(client: ApiClient, plate: string): Promise<HaulPlateCheck> {
  return unwrap(client.GET('/api/v1/hauls/check-plate', { params: { query: { plate } } }));
}

export function scanHaulPlate(client: ApiClient, fieldSessionId: string, photoUri: string): Promise<PlateScan> {
  const form = scanForm(fieldSessionId, photoUri);

  return unwrap(
    client.POST('/api/v1/hauls/scan', { body: form as unknown as ScanBody, bodySerializer: () => form }),
  );
}

export async function createHaul(client: ApiClient, body: HaulStoreRequest): Promise<HaulTicket> {
  const result = await unwrap(client.POST('/api/v1/hauls', { body }));

  return result.data;
}

export function fetchHaulSession(client: ApiClient): Promise<SessionHauls> {
  return unwrap(client.GET('/api/v1/hauls/session'));
}

export async function fetchHaul(client: ApiClient, haulId: string): Promise<HaulTicket> {
  const result = await unwrap(client.GET('/api/v1/hauls/{haul}', { params: { path: { haul: haulId } } }));

  return result.data;
}

export async function updateHaul(client: ApiClient, haulId: string, body: HaulUpdateRequest): Promise<HaulTicket> {
  const result = await unwrap(client.PUT('/api/v1/hauls/{haul}', { params: { path: { haul: haulId } }, body }));

  return result.data;
}

export async function deleteHaul(client: ApiClient, haulId: string): Promise<void> {
  await unwrap(client.DELETE('/api/v1/hauls/{haul}', { params: { path: { haul: haulId } } }));
}

export function fetchMachineWorkOptions(client: ApiClient): Promise<MachineWorkOptions> {
  return unwrap(client.GET('/api/v1/machine-works/options'));
}

export async function searchMachines(client: ApiClient, query: string): Promise<MachineSearchResult[]> {
  const result = await unwrap(client.GET('/api/v1/machine-works/machines', { params: { query: { q: query } } }));

  return result.machines;
}

export function checkMachinePlate(client: ApiClient, plate: string): Promise<MachinePlateCheck> {
  return unwrap(client.GET('/api/v1/machine-works/check-plate', { params: { query: { plate } } }));
}

export function scanMachinePlate(client: ApiClient, fieldSessionId: string, photoUri: string): Promise<MachinePlateScan> {
  const form = scanForm(fieldSessionId, photoUri);

  return unwrap(
    client.POST('/api/v1/machine-works/scan', { body: form as unknown as ScanBody, bodySerializer: () => form }),
  );
}

export async function createMachineWork(client: ApiClient, body: MachineWorkStoreRequest): Promise<MachineWorkTicket> {
  const result = await unwrap(client.POST('/api/v1/machine-works', { body }));

  return result.data;
}

export function fetchMachineWorkSession(client: ApiClient): Promise<SessionMachineWorks> {
  return unwrap(client.GET('/api/v1/machine-works/session'));
}

export async function fetchMachineWork(client: ApiClient, workId: string): Promise<MachineWorkTicket> {
  const result = await unwrap(
    client.GET('/api/v1/machine-works/{machineWork}', { params: { path: { machineWork: workId } } }),
  );

  return result.data;
}

export async function updateMachineWork(
  client: ApiClient,
  workId: string,
  body: MachineWorkUpdateRequest,
): Promise<MachineWorkTicket> {
  const result = await unwrap(
    client.PUT('/api/v1/machine-works/{machineWork}', { params: { path: { machineWork: workId } }, body }),
  );

  return result.data;
}

export async function deleteMachineWork(client: ApiClient, workId: string): Promise<void> {
  await unwrap(client.DELETE('/api/v1/machine-works/{machineWork}', { params: { path: { machineWork: workId } } }));
}

export function fetchFuelReceiptOptions(client: ApiClient): Promise<FuelReceiptOptions> {
  return unwrap(client.GET('/api/v1/fuel-receipts/options'));
}

export async function searchFuelVehicles(client: ApiClient, query: string): Promise<FuelVehicleSearchResult[]> {
  const result = await unwrap(client.GET('/api/v1/fuel-receipts/vehicles', { params: { query: { q: query } } }));

  return result.vehicles;
}

export function checkFuelPlate(client: ApiClient, plate: string): Promise<FuelPlateCheck> {
  return unwrap(client.GET('/api/v1/fuel-receipts/check-plate', { params: { query: { plate } } }));
}

export function scanFuelPlate(client: ApiClient, fieldSessionId: string, photoUri: string): Promise<FuelPlateScan> {
  const form = scanForm(fieldSessionId, photoUri);

  return unwrap(
    client.POST('/api/v1/fuel-receipts/scan', { body: form as unknown as ScanBody, bodySerializer: () => form }),
  );
}

export async function createFuelReceipt(client: ApiClient, body: FuelReceiptStoreRequest): Promise<FuelReceiptTicket> {
  const result = await unwrap(client.POST('/api/v1/fuel-receipts', { body }));

  return result.data;
}

export function fetchFuelReceiptSession(client: ApiClient): Promise<SessionFuelReceipts> {
  return unwrap(client.GET('/api/v1/fuel-receipts/session'));
}

export async function fetchFuelReceipt(client: ApiClient, receiptId: string): Promise<FuelReceiptTicket> {
  const result = await unwrap(
    client.GET('/api/v1/fuel-receipts/{fuelReceipt}', { params: { path: { fuelReceipt: receiptId } } }),
  );

  return result.data;
}

export async function updateFuelReceipt(
  client: ApiClient,
  receiptId: string,
  body: FuelReceiptUpdateRequest,
): Promise<FuelReceiptTicket> {
  const result = await unwrap(
    client.PUT('/api/v1/fuel-receipts/{fuelReceipt}', { params: { path: { fuelReceipt: receiptId } }, body }),
  );

  return result.data;
}

export async function deleteFuelReceipt(client: ApiClient, receiptId: string): Promise<void> {
  await unwrap(client.DELETE('/api/v1/fuel-receipts/{fuelReceipt}', { params: { path: { fuelReceipt: receiptId } } }));
}
