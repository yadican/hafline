import { RequireSession } from '@/features/field-session/require-session';
import { FuelReceiptForm } from '@/features/fuel-receipt/form';

export default function NewFuelReceiptScreen() {
  return <RequireSession>{(session) => <FuelReceiptForm mode="create" fieldSessionId={session.id} />}</RequireSession>;
}
