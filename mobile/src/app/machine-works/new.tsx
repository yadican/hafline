import { SessionGate } from '@/features/field-session/session-gate';
import { MachineWorkForm } from '@/features/machine-work/form';

export default function NewMachineWorkScreen() {
  return <SessionGate>{(session) => <MachineWorkForm mode="create" fieldSessionId={session.id} />}</SessionGate>;
}
