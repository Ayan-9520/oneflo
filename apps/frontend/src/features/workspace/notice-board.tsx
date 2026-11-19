import type { ReactNode } from 'react'
import { Children } from 'react'
import { useQuery, useQueryClient } from '@tanstack/react-query'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { ErrorState } from '@/components/ui/error-state'
import { Skeleton } from '@/components/ui/skeleton'
import { ApiError, apiData } from '@/lib/api'

type Note = { id: number; title: string; body: string; read_at: string | null }
type Post = { id: number; kind: string; title: string; body: string; occurs_on: string | null }
type Reminders = {
  birthdays: Array<{ name: string; day: string }>
  anniversaries: Array<{ name: string; years: number }>
}

export function NoticeBoard() {
  const { organizationId } = useSession()
  const queryClient = useQueryClient()
  const enabled = organizationId !== null
  const notes = useQuery({
    queryKey: ['notifications', organizationId],
    enabled,
    queryFn: () => apiData<Note[]>('/api/v1/notifications'),
  })
  const posts = useQuery({
    queryKey: ['posts', organizationId],
    enabled,
    queryFn: () => apiData<Post[]>('/api/v1/posts'),
  })
  const reminders = useQuery({
    queryKey: ['reminders', organizationId],
    enabled,
    queryFn: () => apiData<Reminders>('/api/v1/reminders'),
  })

  if (notes.isPending && posts.isPending && reminders.isPending) return <Skeleton className="h-24 w-full" />

  const announcements = (posts.data ?? []).filter((post) => post.kind === 'announcement')
  const occasions = (posts.data ?? []).filter((post) => post.kind === 'event' || post.kind === 'news')
  const birthdays = reminders.data?.birthdays ?? []
  const anniversaries = reminders.data?.anniversaries ?? []

  return (
    <div className="flex flex-col gap-6">
      {notes.isError ? <ErrorState title="Account notices could not be loaded" description={notes.error instanceof ApiError ? notes.error.message : 'Try again.'} /> : null}
      {posts.isError ? <ErrorState title="Announcements could not be loaded" description={posts.error instanceof ApiError ? posts.error.message : 'Try again.'} /> : null}
      {reminders.isError ? <ErrorState title="Birthdays could not be loaded" description={reminders.error instanceof ApiError ? reminders.error.message : 'Try again.'} /> : null}

      <NoticeGroup title="Important announcements" empty="No announcement is saved for this business.">
        {announcements.map((post) => (
          <Card key={post.id} title={post.title} body={post.body} meta="Announcement" />
        ))}
      </NoticeGroup>

      <NoticeGroup title="Birthday wishes" empty="No birthday is recorded this month.">
        {birthdays.map((row) => (
          <Card key={`${row.name}-${row.day}`} title={`Happy birthday, ${row.name}`} body="This wish uses the date of birth saved on the employee." meta={row.day} />
        ))}
      </NoticeGroup>

      <NoticeGroup title="Special occasions" empty="No work anniversary or saved event is on record this month.">
        {anniversaries.map((row) => (
          <Card key={`${row.name}-${row.years}`} title={`${row.name} · ${row.years} ${row.years === 1 ? 'year' : 'years'}`} body="Work anniversary from the joining date saved on the employee." meta="Work anniversary" />
        ))}
        {occasions.map((post) => (
          <Card key={post.id} title={post.title} body={post.body} meta={post.occurs_on ? `${post.kind} · ${post.occurs_on}` : post.kind} />
        ))}
      </NoticeGroup>

      <NoticeGroup title="Other notices" empty="No other notice is waiting.">
        {(notes.data ?? []).map((note) => (
          <Card
            key={note.id}
            title={note.title}
            body={note.body}
            meta={note.read_at ? 'Read' : 'Unread'}
            action={note.read_at ? null : (
              <Button variant="outline" onClick={() => void apiData(`/api/v1/notifications/${note.id}/read`, { method: 'POST' }).then(() => queryClient.invalidateQueries({ queryKey: ['notifications'] }))}>Mark read</Button>
            )}
          />
        ))}
      </NoticeGroup>
    </div>
  )
}

function NoticeGroup({ title, empty, children }: { title: string; empty: string; children: ReactNode }) {
  const filled = Children.toArray(children).length > 0
  return (
    <section>
      <h2 className="text-sm font-semibold">{title}</h2>
      {filled ? <div className="mt-2 flex flex-col gap-2">{children}</div> : <p className="mt-2 text-sm text-muted-foreground">{empty}</p>}
    </section>
  )
}

function Card({ title, body, meta, action }: { title: string; body: string; meta: string; action?: ReactNode }) {
  return (
    <article className="rounded-2xl border border-border bg-card px-4 py-3 text-sm">
      <p className="font-semibold">{title}</p>
      <p className="mt-1 text-muted-foreground">{body}</p>
      <p className="mt-2 text-xs font-medium text-[#3a35e0]">{meta}</p>
      {action ? <div className="mt-3">{action}</div> : null}
    </article>
  )
}
