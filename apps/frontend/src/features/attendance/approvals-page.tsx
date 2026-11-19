import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { PageHeader } from '@/components/ui/page-header'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData } from '@/lib/api'

type QueueItem = { id: number; employee_name: string | null; status: string; current_step: string | null; work_date?: string; type?: string; starts_on?: string; ends_on?: string; leave_type?: string | null; days?: string }

export function ApprovalsPage() {
  const { organizationId, permissions } = useSession()
  const attendance = permissions.has('attendance.approve')
  const leave = permissions.has('leave.approve')
  const attendanceQueue = useQuery({
    queryKey: ['attendance-queue', organizationId],
    enabled: organizationId !== null && attendance,
    queryFn: () => apiData<QueueItem[]>('/api/v1/attendance/requests'),
  })
  const leaveQueue = useQuery({
    queryKey: ['leave-queue', organizationId],
    enabled: organizationId !== null && leave,
    queryFn: () => apiData<QueueItem[]>('/api/v1/leave/queue'),
  })

  if (organizationId === null) return <EmptyState title="Select a business" description="Approval queues follow the active business." />

  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Approvals" description="Manager and HR steps for attendance corrections and leave." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Approvals' }]} />
      {attendance ? <Queue title="Attendance" path="/api/v1/attendance/requests" query={attendanceQueue} invalidate="attendance-queue" /> : null}
      {leave ? <Queue title="Leave" path="/api/v1/leave/applications" query={leaveQueue} invalidate="leave-queue" /> : null}
    </div>
  )
}

function Queue({ title, path, query, invalidate }: { title: string; path: string; query: ReturnType<typeof useQuery<QueueItem[]>>; invalidate: string }) {
  if (query.isPending) return <Skeleton className="h-24 w-full" />
  if (query.isError) return <ErrorState title={`${title} approvals could not be loaded`} description={query.error instanceof ApiError ? query.error.message : 'Try again.'} />
  if (query.data.length === 0) return <EmptyState title={`No ${title.toLowerCase()} approvals`} description="Pending requests for people you can see appear here." />
  return (
    <section className="flex flex-col gap-3">
      <h2 className="text-base font-semibold">{title}</h2>
      <ul className="flex flex-col gap-2">
        {query.data.map((item) => <QueueRow key={item.id} item={item} path={`${path}/${item.id}/decision`} invalidate={invalidate} />)}
      </ul>
    </section>
  )
}

function QueueRow({ item, path, invalidate }: { item: QueueItem; path: string; invalidate: string }) {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [pending, setPending] = useState(false)

  async function decide(approve: boolean) {
    setPending(true)
    try {
      await apiData(path, { method: 'POST', body: JSON.stringify({ approve }) })
      notify(approve ? 'Approved.' : 'Rejected.', 'success')
      await queryClient.invalidateQueries({ queryKey: [invalidate] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The decision could not be saved.', 'danger')
    } finally {
      setPending(false)
    }
  }

  const detail = item.work_date ? `${item.work_date} · ${item.type}` : `${item.leave_type ?? 'Leave'} · ${item.starts_on} to ${item.ends_on} · ${item.days} days`
  return (
    <li className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border px-4 py-3 text-sm">
      <div>
        <p className="font-medium">{item.employee_name}</p>
        <p className="text-muted-foreground">{detail} · {item.current_step}</p>
      </div>
      <div className="flex gap-2">
        <Button disabled={pending} onClick={() => void decide(true)}>Approve</Button>
        <Button variant="outline" disabled={pending} onClick={() => void decide(false)}>Reject</Button>
      </div>
    </li>
  )
}
