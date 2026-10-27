import { useQuery } from '@tanstack/react-query';

import { fetchDashboard } from '@/api/endpoints';
import { useApiClient } from '@/auth/auth-provider';

const keys = {
  summary: ['dashboard'] as const,
};

export function useDashboard() {
  const client = useApiClient();

  return useQuery({
    queryKey: keys.summary,
    queryFn: () => fetchDashboard(client),
  });
}
