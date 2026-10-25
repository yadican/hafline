import { QueryClient } from '@tanstack/react-query';

import { ApiError } from '@/api/errors';

export const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      staleTime: 30 * 1000,
      retry: (failureCount, error) => {
        if (error instanceof ApiError && error.isClientError) {
          return false;
        }

        return failureCount < 2;
      },
    },
  },
});
