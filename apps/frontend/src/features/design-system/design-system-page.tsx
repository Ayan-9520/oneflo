import { zodResolver } from '@hookform/resolvers/zod'
import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { DataTable, type Column } from '@/components/ui/data-table'
import { DatePicker } from '@/components/ui/date-picker'
import { Dialog } from '@/components/ui/dialog'
import { Drawer } from '@/components/ui/drawer'
import { DropdownMenu } from '@/components/ui/dropdown-menu'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input } from '@/components/ui/input'
import { FilterBar } from '@/components/ui/filter-bar'
import { PageHeader } from '@/components/ui/page-header'
import { Pagination } from '@/components/ui/pagination'
import { SearchField } from '@/components/ui/search-field'
import { Select } from '@/components/ui/select'
import { SkeletonRows } from '@/components/ui/skeleton'
import { Tabs } from '@/components/ui/tabs'
import { useToast } from '@/components/ui/toast'

const swatches = [
  ['Primary', '#2A1878'],
  ['Primary dark', '#1A0F52'],
  ['Accent', '#FF2D87'],
  ['Background', '#F7F6FB'],
  ['Surface', '#FFFFFF'],
  ['Text', '#1C1433'],
  ['Muted', '#6D6586'],
  ['Success', '#16A36A'],
  ['Warning', '#E9A23B'],
  ['Danger', '#DC4C4C'],
] as const

const catalog = [
  { name: 'Button', group: 'Controls' },
  { name: 'Input', group: 'Controls' },
  { name: 'Select', group: 'Controls' },
  { name: 'Date picker', group: 'Controls' },
  { name: 'Badge', group: 'Controls' },
  { name: 'Dialog', group: 'Overlays' },
  { name: 'Drawer', group: 'Overlays' },
  { name: 'Dropdown', group: 'Overlays' },
  { name: 'Tooltip', group: 'Overlays' },
  { name: 'Toast', group: 'Overlays' },
  { name: 'Data table', group: 'Data' },
  { name: 'Pagination', group: 'Data' },
  { name: 'Empty state', group: 'Data' },
  { name: 'Skeleton', group: 'Data' },
  { name: 'Error state', group: 'Data' },
] as const

type CatalogRow = (typeof catalog)[number]

const columns: readonly Column<CatalogRow>[] = [
  { id: 'name', header: 'Component', cell: (row) => row.name },
  { id: 'group', header: 'Group', cell: (row) => <Badge tone="info">{row.group}</Badge> },
]

const formSchema = z.object({
  label: z.string().min(2, 'Enter at least 2 characters.'),
})

type FormValues = z.infer<typeof formSchema>

const pageSize = 5

export function DesignSystemPage() {
  const { notify } = useToast()
  const [date, setDate] = useState('')
  const [dialogOpen, setDialogOpen] = useState(false)
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [group, setGroup] = useState('all')
  const [page, setPage] = useState(1)
  const form = useForm<FormValues>({
    resolver: zodResolver(formSchema),
    defaultValues: { label: '' },
  })

  const filtered = useMemo(() => {
    return catalog.filter((row) => {
      const matchesQuery = row.name.toLowerCase().includes(query.trim().toLowerCase())
      const matchesGroup = group === 'all' || row.group === group
      return matchesQuery && matchesGroup
    })
  }, [group, query])

  const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize))
  const currentPage = Math.min(page, pageCount)
  const rows = filtered.slice((currentPage - 1) * pageSize, currentPage * pageSize)

  return (
    <div>
      <PageHeader
        title="Design system"
        description="Original OneFlo components for the enterprise workspace."
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Design system' }]}
      />
      <Tabs
        tabs={[
          {
            id: 'foundations',
            label: 'Foundations',
            content: (
              <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                {swatches.map(([name, value]) => (
                  <div key={name} className="overflow-hidden rounded-lg border border-border bg-card shadow-[var(--shadow-card)]">
                    <div className="h-16" style={{ backgroundColor: value }} />
                    <div className="px-3 py-2">
                      <p className="text-sm font-medium">{name}</p>
                      <p className="text-xs text-muted-foreground">{value}</p>
                    </div>
                  </div>
                ))}
              </div>
            ),
          },
          {
            id: 'controls',
            label: 'Controls',
            content: (
              <div className="grid gap-6 lg:grid-cols-2">
                <div className="flex flex-wrap gap-2">
                  <Button>Primary</Button>
                  <Button variant="secondary">Secondary</Button>
                  <Button variant="outline">Outline</Button>
                  <Button variant="ghost">Ghost</Button>
                  <Button variant="danger">Danger</Button>
                </div>
                <form
                  className="flex flex-col gap-4 rounded-xl border border-border bg-card p-5 shadow-[var(--shadow-card)]"
                  onSubmit={form.handleSubmit((values) => {
                    notify(`Saved locally: ${values.label}`, 'success')
                    form.reset()
                  })}
                >
                  <Field label="Sample label" htmlFor="sample-label" error={form.formState.errors.label?.message}>
                    <Input id="sample-label" {...form.register('label')} />
                  </Field>
                  <Field label="Status" htmlFor="sample-status">
                    <Select
                      id="sample-status"
                      defaultValue="open"
                      options={[
                        { value: 'open', label: 'Open' },
                        { value: 'closed', label: 'Closed' },
                      ]}
                    />
                  </Field>
                  <Field label="Date" htmlFor="sample-date">
                    <DatePicker id="sample-date" value={date} onChange={setDate} />
                  </Field>
                  <div className="flex flex-wrap gap-2">
                    <Badge tone="success">Success</Badge>
                    <Badge tone="warning">Warning</Badge>
                    <Badge tone="danger">Danger</Badge>
                    <Badge>Neutral</Badge>
                  </div>
                  <Button type="submit" disabled={form.formState.isSubmitting}>
                    Validate form
                  </Button>
                </form>
              </div>
            ),
          },
          {
            id: 'overlays',
            label: 'Overlays',
            content: (
              <div className="flex flex-wrap gap-2">
                <Button onClick={() => setDialogOpen(true)}>Open dialog</Button>
                <Button variant="outline" onClick={() => setDrawerOpen(true)}>
                  Open drawer
                </Button>
                <DropdownMenu
                  label="Example menu"
                  trigger={<span className="inline-flex h-10 items-center rounded-md border border-border bg-card px-4 text-sm">Dropdown</span>}
                  items={[
                    { id: 'info', label: 'Show an info toast', onSelect: () => notify('This menu only demonstrates the component.') },
                    { id: 'warn', label: 'Show a warning toast', onSelect: () => notify('No record was changed.', 'warning') },
                  ]}
                />
                <Dialog open={dialogOpen} title="Dialog" description="A focused panel for a single decision." onClose={() => setDialogOpen(false)}>
                  <p className="text-sm text-muted-foreground">Dialogs stay closed until a person opens them.</p>
                </Dialog>
                <Drawer open={drawerOpen} title="Drawer" onClose={() => setDrawerOpen(false)}>
                  <p className="text-sm text-muted-foreground">Drawers hold secondary detail without leaving the page.</p>
                </Drawer>
              </div>
            ),
          },
          {
            id: 'data',
            label: 'Data',
            content: (
              <div className="flex flex-col gap-4">
                <FilterBar>
                  <SearchField
                    aria-label="Filter components"
                    placeholder="Filter components"
                    value={query}
                    onChange={(event) => {
                      setQuery(event.target.value)
                      setPage(1)
                    }}
                    className="sm:max-w-xs"
                  />
                  <Select
                    aria-label="Component group"
                    className="sm:max-w-48"
                    value={group}
                    options={[
                      { value: 'all', label: 'All groups' },
                      { value: 'Controls', label: 'Controls' },
                      { value: 'Overlays', label: 'Overlays' },
                      { value: 'Data', label: 'Data' },
                    ]}
                    onChange={(event) => {
                      setGroup(event.target.value)
                      setPage(1)
                    }}
                  />
                </FilterBar>
                {rows.length === 0 ? (
                  <EmptyState title="No components match" description="Clear the search or choose another group." />
                ) : (
                  <DataTable columns={columns} rows={rows} getRowId={(row) => row.name} caption="Design system components" />
                )}
                <Pagination page={currentPage} pageCount={pageCount} onPageChange={setPage} />
                <SkeletonRows />
                <ErrorState title="Example error" description="API failures use this pattern. This example is not a live failure." />
              </div>
            ),
          },
        ]}
      />
    </div>
  )
}
