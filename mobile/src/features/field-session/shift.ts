import type { FieldSession, Shift } from '@/api/types';

/** Only preselects the shift picker; the user can always choose the other shift. */
const DAY_SHIFT_START_HOUR = 7;
const NIGHT_SHIFT_START_HOUR = 19;

/** A shift lasts about 12 hours, so an older choice belongs to an earlier shift and is asked again. */
const SESSION_MAX_AGE_MS = 14 * 60 * 60 * 1000;

export function suggestedShift(now = new Date()): Shift {
  const hour = now.getHours();

  return hour >= DAY_SHIFT_START_HOUR && hour < NIGHT_SHIFT_START_HOUR ? 'day' : 'night';
}

export function isSessionCurrent(session: FieldSession, now = Date.now()): boolean {
  return now - new Date(session.locked_at).getTime() < SESSION_MAX_AGE_MS;
}
