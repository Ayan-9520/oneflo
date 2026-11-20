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

type Person = { id: number; full_name: string; employee_code?: string }
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
    enabled: organizationId !== null,
    queryFn: () => apiData<Person[]>('/api/v1/employees'),
  })
  const [pending, setPending] = useState(false)
  const people = employees.data ?? []

  async function createTeam(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()
    const form = event.currentTarget
    const body = new FormData(form)
    const leader = String(body.get('leader_employee_id') ?? '')
    setPending(true)
    try {
      await apiData('/api/v1/teams', {
        method: 'POST',
        body: JSON.stringify({
          name: String(body.get('name') ?? '').trim(),
          leader_employee_id: leader === '' ? null : Number(leader),
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

  const removeMember = useMutation({
    mutationFn: (input: { teamId: number; employeeId: number }) =>
      apiData(`/api/v1/teams/${input.teamId}/members/${input.employeeId}`, { method: 'DELETE' }),
    onSuccess: async () => {
      notify('Team member removed.', 'success')
      await queryClient.invalidateQueries({ queryKey: ['teams'] })
    },
    onError: (error) => notify(error instanceof ApiError ? error.message : 'The member could not be removed.', 'danger'),
  })

  if (organizationId === null) {
    return <EmptyState title="Select a business" description="Teams belong to the active business." />
  }

  return (
    <div className="mx-auto flex max-w-4xl flex-col gap-6">
      <PageHeader
        title="Team"
        description="A team is a name, one leader, and members. The leader and members must already be employees in People."
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Team' }]}
      />

      {people.length === 0 && !employees.isPending ? (
        <EmptyState title="Add a person first" description="People is empty for this business, so a leader or member cannot be chosen yet." />
      ) : null}

      {canUpdate ? (
        <form className="grid gap-4 rounded-3xl border border-border bg-card p-5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]" onSubmit={(event) => void createTeam(event)}>
          <Field label="Team name" htmlFor="team-name">
            <Input id="team-name" name="name" required placeholder="Sales" />
          </Field>
          <Field label="Leader" htmlFor="team-leader">
            <Select
              id="team-leader"
              name="leader_employee_id"
              options={[{ value: '', label: 'No leader yet' }, ...people.map((employee) => ({ value: String(employee.id), label: personLabel(employee) }))]}
            />
          </Field>
          <div className="flex items-end">
            <Button type="submit" disabled={pending}>{pending ? 'Saving…' : 'Create team'}</Button>
          </div>
        </form>
      ) : null}

      {teams.isPending ? (
        <Skeleton className="h-32 w-full" />
      ) : teams.isError ? (
        <ErrorState title="Teams could not be loaded" description={teams.error instanceof ApiError ? teams.error.message : 'Try again.'} />
      ) : teams.data.length === 0 ? (
        <EmptyState title="No teams" description="Create a team after at least one employee exists in People." />
      ) : (
        <ul className="flex flex-col gap-4">
          {teams.data.map((team) => {
            const memberIds = new Set(team.members.map((member) => member.id))
            const available = people.filter((employee) => !memberIds.has(employee.id))
            return (
              <li key={team.id} className="rounded-3xl border border-border bg-card p-5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h2 className="text-lg font-semibold">{team.name}</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Leader: {team.leader?.full_name ?? 'Not assigned'}</p>
                  </div>
                  <p className="rounded-full bg-[#f4f1fb] px-3 py-1 text-xs font-semibold text-[#3a35e0]">{team.members.length} {team.members.length === 1 ? 'member' : 'members'}</p>
                </div>
                {team.members.length === 0 ? <p className="mt-4 text-sm text-muted-foreground">No members yet.</p> : (
                  <ul className="mt-4 flex flex-wrap gap-2">
                    {team.members.map((member) => (
                      <li key={member.id} className="inline-flex items-center gap-2 rounded-full border border-border px-3 py-1 text-sm">
                        {member.full_name || 'Employee'}
                        {canUpdate ? (
                          <button type="button" className="text-xs font-semibold text-[#3a35e0]" onClick={() => removeMember.mutate({ teamId: team.id, employeeId: member.id })}>Remove</button>
                        ) : null}
                      </li>
                    ))}
                  </ul>
                )}
                {canUpdate ? (
                  <form
                    className="mt-4 flex flex-wrap items-end gap-2"
                    onSubmit={(event) => {
                      event.preventDefault()
                      const employeeId = Number(new FormData(event.currentTarget).get('employee_id'))
                      if (!employeeId) {
                        notify('Choose an employee from People.', 'danger')
                        return
                      }
                      addMember.mutate({ teamId: team.id, employeeId })
                      event.currentTarget.reset()
                    }}
                  >
                    <div className="min-w-56 flex-1">
                      <Field label="Add a member" htmlFor={`member-${team.id}`}>
                        <Select
                          id={`member-${team.id}`}
                          name="employee_id"
                          placeholder={available.length === 0 ? 'Everyone is already on this team' : 'Choose an employee'}
                          options={available.map((employee) => ({ value: String(employee.id), label: personLabel(employee) }))}
                        />
                      </Field>
                    </div>
                    <Button type="submit" variant="outline" disabled={addMember.isPending || available.length === 0}>Add member</Button>
                  </form>
                ) : null}
              </li>
            )
          })}
        </ul>
      )}
    </div>
  )
}

function personLabel(employee: Person): string {
  return employee.employee_code ? `${employee.employee_code} · ${employee.full_name}` : employee.full_name
}
