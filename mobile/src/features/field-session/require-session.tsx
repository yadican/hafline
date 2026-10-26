import type { ReactNode } from 'react';

import { ApiError } from '@/api/errors';
import type { FieldSession } from '@/api/types';
import { ErrorState, LoadingState } from '@/components/screen-state';

import { useCurrentSession } from './queries';

type RequireSessionProps = {
  children: (session: FieldSession) => ReactNode;
};

/** Renders children only while the user has an open field session. */
export function RequireSession({ children }: RequireSessionProps) {
  const session = useCurrentSession();

  if (session.isPending) {
    return <LoadingState />;
  }

  if (session.isError) {
    return <ErrorState error={session.error} onRetry={() => void session.refetch()} />;
  }

  if (!session.data) {
    return <ErrorState error={new ApiError('Aktif saha oturumu yok. Önce oturum başlatın.', 0)} />;
  }

  return children(session.data);
}
