import type { components } from './schema';

export type User = components['schemas']['User'];
export type LoginRequest = components['schemas']['LoginRequest'];
export type LoginResponse = components['schemas']['LoginResponse'];

export type Shift = 'day' | 'night';

// The endpoints below have no response schema in Swagger yet, so their shapes mirror FieldHaulService.

export type NamedOption = {
  id: string;
  name: string;
};

export type ShiftOption = {
  value: Shift;
  label: string;
};

export type HaulOptions = {
  sites: NamedOption[];
  shifts: ShiftOption[];
};

export type FieldSession = {
  id: string;
  site_id: string;
  site_name: string | null;
  dump_site_id: string;
  dump_site_name: string | null;
  shift: Shift;
  shift_label: string;
  locked_at: string;
};
