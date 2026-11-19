import { useLocation } from 'react-router'

import { canAccess, useSession } from '@/app/session'
import { findNavigation } from '@/app/navigation'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { PageHeader } from '@/components/ui/page-header'

export function ModulePage() {
  const { pathname } = useLocation()
  const { permissions } = useSession()
  const item = findNavigation(pathname)

  if (!item) {
    return <ErrorState title="This page does not exist" description="The address is not part of the OneFlo workspace." />
  }

  if (!canAccess(item.access, permissions)) {
    return (
      <ErrorState
        title="This area is not available for this account"
        description="The signed-in account does not have permission to open this page in the active business."
      />
    )
  }

  return (
    <div>
      <PageHeader
        title={item.label}
        description={item.description}
        crumbs={[{ label: 'Workspace', to: '/' }, { label: item.label }]}
      />
      <EmptyState
        title="Waiting for the API"
        description={`${item.label} will list records from the OneFlo API. This screen does not create or display sample business data.`}
      />
    </div>
  )
}
