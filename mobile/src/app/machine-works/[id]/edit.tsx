import { useLocalSearchParams } from 'expo-router';

import { ErrorState, LoadingState } from '@/components/screen-state';
import { MachineWorkForm } from '@/features/machine-work/form';
import { useMachineWorkTicket } from '@/features/machine-work/queries';

export default function EditMachineWorkScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const ticket = useMachineWorkTicket(id);

  if (ticket.isPending) {
    return <LoadingState />;
  }

  if (ticket.isError) {
    return <ErrorState error={ticket.error} onRetry={() => void ticket.refetch()} />;
  }

  return <MachineWorkForm mode="edit" ticket={ticket.data} />;
}
