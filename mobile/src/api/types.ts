import type { components } from './schema';

type Schemas = components['schemas'];

export type User = Schemas['User'];
export type LoginRequest = Schemas['LoginRequest'];
export type LoginResponse = Schemas['LoginResponse'];

export type Dashboard = Schemas['DashboardResponse'];
export type DashboardCounts = Schemas['DashboardCounts'];
export type DashboardRecentItem = Schemas['DashboardRecentItem'];

export type NamedOption = Schemas['NamedOption'];
export type ShiftOption = Schemas['ShiftOption'];
export type Shift = ShiftOption['value'];
export type HaulOptions = Schemas['HaulOptionsResponse'];

export type FieldSessionRequest = Schemas['FieldSessionRequest'];
export type FieldSession = Schemas['FieldSession'];

export type HaulVehicleSearchResult = Schemas['HaulVehicleSearchResponse']['vehicles'][number];
export type HaulPlateCheck = Schemas['HaulPlateCheck'];
export type PlateScan = Schemas['PlateScanResult'];
export type HaulStoreRequest = Schemas['HaulStoreRequest'];
export type HaulUpdateRequest = Schemas['HaulUpdateRequest'];
export type HaulTicket = Schemas['HaulTicket'];
export type SessionHauls = Schemas['SessionHaulsResponse'];

export type MachineWorkOptions = Schemas['MachineWorkOptionsResponse'];
export type MachineOperatorOption = Schemas['MachineOperatorOption'];
export type MachineSearchResult = Schemas['MachineSearchResponse']['machines'][number];
export type MachinePlateCheck = Schemas['MachinePlateCheck'];
export type MachinePlateScan = Schemas['MachinePlateScanResult'];
export type MachineWorkStoreRequest = Schemas['MachineWorkStoreRequest'];
export type MachineWorkUpdateRequest = Schemas['MachineWorkUpdateRequest'];
export type MachineWorkTicket = Schemas['MachineWorkTicket'];
export type SessionMachineWorks = Schemas['SessionMachineWorksResponse'];

export type FuelReceiptOptions = Schemas['FuelReceiptOptionsResponse'];
export type FuelStationOption = Schemas['FuelStationOption'];
export type FuelVehicleSearchResult = Schemas['FuelVehicleSearchResponse']['vehicles'][number];
export type FuelPlateCheck = Schemas['FuelPlateCheck'];
export type FuelPlateScan = Schemas['FuelPlateScanResult'];
export type FuelReceiptStoreRequest = Schemas['FuelReceiptStoreRequest'];
export type FuelReceiptUpdateRequest = Schemas['FuelReceiptUpdateRequest'];
export type FuelReceiptTicket = Schemas['FuelReceiptTicket'];
export type SessionFuelReceipts = Schemas['SessionFuelReceiptsResponse'];
