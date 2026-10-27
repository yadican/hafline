import { useLocalSearchParams } from 'expo-router';

import { ErrorState, LoadingState } from '@/components/screen-state';
import { FuelReceiptForm } from '@/features/fuel-receipt/form';
import { useFuelReceiptTicket } from '@/features/fuel-receipt/queries';

export default function EditFuelReceiptScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const ticket = useFuelReceiptTicket(id);

  if (ticket.isPending) {
    return <LoadingState />;
  }

  if (ticket.isError) {
    return <ErrorState error={ticket.error} onRetry={() => void ticket.refetch()} />;
  }

  return <FuelReceiptForm mode="edit" ticket={ticket.data} />;
}
