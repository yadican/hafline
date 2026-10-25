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

export type PlateScan = {
  success: boolean;
  scan_id: string;
  field_session_id: string;
  plate: string | null;
  confidence: number | null;
  matched_regex: boolean;
  vehicle: {
    id: string | null;
    plate: string | null;
    found: boolean;
  };
  owner_on_date: boolean;
  can_create: boolean;
  message: string | null;
};

export type HaulTicket = {
  id: string;
  title: string;
  ticket_no: string;
  hauled_at: string;
  hauled_at_label: string;
  plate: string | null;
  site_name: string | null;
  dump_site_name: string | null;
  shift: Shift;
  shift_label: string;
  tonnage: number;
  qr_payload: string;
  photo_url: string | null;
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
