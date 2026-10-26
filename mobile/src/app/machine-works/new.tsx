import { RequireSession } from '@/features/field-session/require-session';
import { MachineWorkForm } from '@/features/machine-work/form';

export default function NewMachineWorkScreen() {
  return <RequireSession>{(session) => <MachineWorkForm mode="create" fieldSessionId={session.id} />}</RequireSession>;
}
