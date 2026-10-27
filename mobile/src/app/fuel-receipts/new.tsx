import { SessionGate } from '@/features/field-session/session-gate';
import { FuelReceiptForm } from '@/features/fuel-receipt/form';

export default function NewFuelReceiptScreen() {
  return <SessionGate>{(session) => <FuelReceiptForm mode="create" fieldSessionId={session.id} />}</SessionGate>;
}
