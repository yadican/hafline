import { useLocalSearchParams } from 'expo-router';

import { ErrorState, LoadingState } from '@/components/screen-state';
import { HaulForm } from '@/features/haul/form';
import { useHaulTicket } from '@/features/haul/queries';

export default function EditHaulScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const ticket = useHaulTicket(id);

  if (ticket.isPending) {
    return <LoadingState />;
  }

  if (ticket.isError) {
    return <ErrorState error={ticket.error} onRetry={() => void ticket.refetch()} />;
  }

  return <HaulForm mode="edit" ticket={ticket.data} />;
}
