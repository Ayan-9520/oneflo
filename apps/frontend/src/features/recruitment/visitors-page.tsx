import { useQuery } from '@tanstack/react-query'

import { useSession } from '@/app/session'
import { DataTable } from '@/components/ui/data-table'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { PageHeader } from '@/components/ui/page-header'
import { Skeleton } from '@/components/ui/skeleton'
import { ApiError, apiData } from '@/lib/api'

type Visitor = {
  id: number
  name: string | null
  phone: string | null
  email: string | null
  location: string | null
  created_at: string | null
}

function when(value: string | null) {
  if (!value) return ''
  const date = new Date(value)
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString()
}

export function VisitorsPage() {
  const { organizationId } = useSession()
  const visitors = useQuery({
    queryKey: ['site-visitors', organizationId],
    enabled: organizationId !== null,
    queryFn: () => apiData<Visitor[]>('/api/v1/site-visitors'),
  })

  return (
    <div>
      <PageHeader
        title="Visitors"
        description="Contact details left on the public home page. These are for the whole site, not one business."
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Visitors' }]}
      />
      {organizationId === null ? (
        <EmptyState title="Select a business" description="Open a business first, then this list is available." />
      ) : visitors.isPending ? (
        <Skeleton className="h-40 w-full" />
      ) : visitors.isError ? (
        <ErrorState title="Visitors could not be loaded" description={visitors.error instanceof ApiError ? visitors.error.message : 'Try again.'} />
      ) : visitors.data.length === 0 ? (
        <EmptyState title="No visitors yet" description="When someone fills the home page popup, their details show up here." />
      ) : (
        <DataTable
          caption="Site visitors"
          rows={visitors.data}
          getRowId={(row) => String(row.id)}
          columns={[
            { id: 'name', header: 'Name', cell: (row) => row.name ?? '' },
            { id: 'phone', header: 'Mobile', cell: (row) => row.phone ?? '' },
            { id: 'location', header: 'Location', cell: (row) => row.location ?? '' },
            { id: 'email', header: 'Email', cell: (row) => row.email ?? '' },
            { id: 'when', header: 'Time', cell: (row) => when(row.created_at) },
          ]}
        />
      )}
    </div>
  )
}
