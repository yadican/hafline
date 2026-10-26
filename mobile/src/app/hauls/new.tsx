import { SessionGate } from '@/features/field-session/session-gate';
import { HaulForm } from '@/features/haul/form';

export default function NewHaulScreen() {
  return (
    <SessionGate requireDumpSite>{(session) => <HaulForm mode="create" fieldSessionId={session.id} />}</SessionGate>
  );
}
