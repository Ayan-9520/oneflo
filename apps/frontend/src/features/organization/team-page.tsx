import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData } from '@/lib/api'

import type { NamedRef } from './employee'

type TeamRecord = {
  id: number
  name: string
  department: NamedRef | null
  leader: { id: number; full_name: string } | null
  members: Array<{ id: number; full_name: string | null }>
}

export function TeamPage() {
  const { organizationId, permissions } = useSession()
  const canUpdate = permissions.has('employees.update')
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const teams = useQuery({
    queryKey: ['teams', organizationId],
    enabled: organizationId !== null,
    queryFn: () => apiData<TeamRecord[]>('/api/v1/teams'),
  })
  const employees = useQuery({
    queryKey: ['employees', organizationId, 'team-picker'],
    enabled: organizationId !== null && canUpdate,
    queryFn: () => apiData<Array<{ id: number; full_name: string }>>('/api/v1/employees'),
  })
  const [pending, setPending] = useState(false)

  async function createTeam(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const body = new FormData(form)
    setPending(true)
    try {
      await apiData('/api/v1/teams', {
        method: 'POST',
        body: JSON.stringify({
          name: String(body.get('name') ?? ''),
          leader_employee_id: numberOrUndefined(body.get('leader_employee_id')),
        }),
      })
      form.reset()
      notify('Team created.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['teams'] })
    } catch (error) {
      notify(error instanceof ApiError ? error.message : 'The team could not be created.', 'danger')
    } finally {
      setPending(false)
    }
  }

  const addMember = useMutation({
    mutationFn: (input: { teamId: number; employeeId: number }) =>
      apiData(`/api/v1/teams/${input.teamId}/members`, {
        method: 'POST',
        body: JSON.stringify({ employee_id: input.employeeId }),
      }),
    onSuccess: async () => {
      notify('Team member added.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['teams'] })
    },
    onError: (error) => notify(error instanceof ApiError ? error.message : 'The member could not be added.', 'danger'),
  })

  if (organizationId === null) {
    return <EmptyState title="Select a business" description="Teams belong to the active business." />
  }

  return (
    <div>
      <PageHeader title="Team" description="Teams and reporting groups in the active business." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Team' }]} />
      {canUpdate ? (
        <form className="mb-6 grid gap-3 sm:grid-cols-3" onSubmit={(event) => void createTeam(event)}>
          <Field label="Team name" htmlFor="team-name">
            <Input id="team-name" name="name" required />
          </Field>
          <Field label="Leader" htmlFor="team-leader">
            <Select
              id="team-leader"
              name="leader_employee_id"
              options={[{ value: '', label: 'No leader' }, ...(employees.data ?? []).map((employee) => ({ value: String(employee.id), label: employee.full_name }))]}
            />
          </Field>
          <div className="flex items-end">
            <Button type="submit" disabled={pending}>
              {pending ? 'Saving…' : 'Create team'}
            </Button>
          </div>
        </form>
      ) : null}
      {teams.isPending ? (
        <Skeleton className="h-32 w-full" />
      ) : teams.isError ? (
        <ErrorState title="Teams could not be loaded" description={teams.error instanceof ApiError ? teams.error.message : 'Try again.'} />
      ) : teams.data.length === 0 ? (
        <EmptyState title="No teams" description="Create a team to group employees in this business." />
      ) : (
        <ul className="flex flex-col gap-4">
          {teams.data.map((team) => (
            <li key={team.id} className="rounded-lg border border-border bg-card p-4">
              <h2 className="font-medium">{team.name}</h2>
              <p className="text-sm text-muted-foreground">Leader: {team.leader?.full_name ?? 'Unassigned'}</p>
              <ul className="mt-2 text-sm">
                {team.members.map((member) => (
                  <li key={member.id}>{member.full_name}</li>
                ))}
              </ul>
              {canUpdate ? (
                <form
                  className="mt-3 flex flex-wrap items-end gap-2"
                  onSubmit={(event) => {
                    event.preventDefault()
                    const employeeId = Number(new FormData(event.currentTarget).get('employee_id'))
                    if (employeeId) addMember.mutate({ teamId: team.id, employeeId })
                  }}
                >
                  <Select
                    aria-label={`Add a member to ${team.name}`}
                    name="employee_id"
                    placeholder="Employee"
                    options={(employees.data ?? []).map((employee) => ({ value: String(employee.id), label: employee.full_name }))}
                  />
                  <Button type="submit" variant="outline" disabled={addMember.isPending}>
                    Add member
                  </Button>
                </form>
              ) : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

function numberOrUndefined(value: FormDataEntryValue | null): number | undefined {
  const text = String(value ?? '')
  return text === '' ? undefined : Number(text)
}
