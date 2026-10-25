import type { components } from './schema';

type Schemas = components['schemas'];

export type User = Schemas['User'];
export type LoginRequest = Schemas['LoginRequest'];
export type LoginResponse = Schemas['LoginResponse'];

export type NamedOption = Schemas['NamedOption'];
export type ShiftOption = Schemas['ShiftOption'];
export type Shift = ShiftOption['value'];
export type HaulOptions = Schemas['HaulOptionsResponse'];

export type FieldSessionRequest = Schemas['FieldSessionRequest'];
export type FieldSession = Schemas['FieldSession'];

export type PlateScan = Schemas['PlateScanResult'];
export type HaulStoreRequest = Schemas['HaulStoreRequest'];
export type HaulTicket = Schemas['HaulTicket'];
