import { RequireSession } from '@/features/field-session/require-session';
import { HaulForm } from '@/features/haul/form';

export default function NewHaulScreen() {
  return <RequireSession>{(session) => <HaulForm mode="create" fieldSessionId={session.id} />}</RequireSession>;
}
