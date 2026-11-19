import { useQuery, useQueryClient } from '@tanstack/react-query'
import { type FormEvent, useEffect, useState } from 'react'
import { useSearchParams } from 'react-router'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { DataTable } from '@/components/ui/data-table'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input, Textarea } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData } from '@/lib/api'

type Named = { id: number; name: string }
type EmployeeRow = { id: number; full_name: string; employee_code: string }

function useOps() {
  const queryClient = useQueryClient()
  const notify = useToast().notify
  const [error, setError] = useState<string | null>(null)
  const [pending, setPending] = useState(false)
  async function send(path: string, body: Record<string, unknown> | null, success: string, keys: string[]) {
    setPending(true)
    setError(null)
    try {
      await apiData(path, body === null ? { method: 'POST' } : { method: 'POST', body: JSON.stringify(body) })
      await Promise.all(keys.map((key) => queryClient.invalidateQueries({ queryKey: [key] })))
      notify(success, 'success')
    } catch (caught) {
      setError(caught instanceof ApiError ? caught.message : 'The request failed.')
    } finally {
      setPending(false)
    }
  }
  return { error, pending, send }
}

type ReviewRow = { id: number; employee_id: number; employee: string | null; cycle: string; status: string; self_score: number | null; manager_score: number | null; promotion_recommended: boolean }

export function PerformancePage() {
  const { organizationId, permissions } = useSession()
  const canManage = permissions.has('performance.manage')
  const reviews = useQuery({ queryKey: ['performance-reviews', organizationId], enabled: organizationId !== null, queryFn: () => apiData<ReviewRow[]>('/api/v1/performance-reviews') })
  const goals = useQuery({ queryKey: ['goals', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; title: string; period: string; target: string; status: string }>>('/api/v1/goals') })
  const kpis = useQuery({ queryKey: ['kpis', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; name: string; unit: string; weight: number }>>('/api/v1/kpis') })
  if (organizationId === null) return <EmptyState title="Select a business" description="Performance stays inside the active business." />
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Performance" description="KPIs, goals, self-assessment, manager scores, and promotion recommendations." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Performance' }]} />
      {kpis.data && kpis.data.length > 0 ? <DataTable caption="KPIs" rows={kpis.data} getRowId={(row) => String(row.id)} columns={[{ id: 'name', header: 'KPI', cell: (row) => row.name }, { id: 'unit', header: 'Unit', cell: (row) => row.unit }, { id: 'weight', header: 'Weight', cell: (row) => String(row.weight) }]} /> : null}
      {reviews.isPending ? <Skeleton className="h-20 w-full" /> : reviews.isError ? <ErrorState title="Reviews could not be loaded" description={reviews.error instanceof ApiError ? reviews.error.message : 'Try again.'} /> : reviews.data.length === 0 ? <EmptyState title="No reviews" description="Open a monthly, quarterly, or annual review." /> : (
        <DataTable caption="Reviews" rows={reviews.data} getRowId={(row) => String(row.id)} columns={[
          { id: 'employee', header: 'Employee', cell: (row) => row.employee ?? '' },
          { id: 'cycle', header: 'Cycle', cell: (row) => row.cycle },
          { id: 'status', header: 'Status', cell: (row) => row.status },
          { id: 'self', header: 'Self score', cell: (row) => row.self_score === null ? '—' : String(row.self_score) },
          { id: 'score', header: 'Manager score', cell: (row) => row.manager_score === null ? '—' : String(row.manager_score) },
          { id: 'promotion', header: 'Promotion', cell: (row) => row.promotion_recommended ? 'Recommended' : '—' },
        ]} />
      )}
      {goals.data && goals.data.length > 0 ? <DataTable caption="Goals" rows={goals.data} getRowId={(row) => String(row.id)} columns={[{ id: 'title', header: 'Goal', cell: (row) => row.title }, { id: 'period', header: 'Period', cell: (row) => row.period }, { id: 'target', header: 'Target', cell: (row) => row.target }]} /> : null}
      {canManage ? <PerformanceForms reviews={reviews.data ?? []} /> : null}
    </div>
  )
}

function PerformanceForms({ reviews }: { reviews: ReviewRow[] }) {
  const { organizationId } = useSession()
  const form = useOps()
  const employees = useQuery({ queryKey: ['employees', organizationId, 'performance'], enabled: organizationId !== null, queryFn: () => apiData<EmployeeRow[]>('/api/v1/employees') })
  const drafts = reviews.filter((row) => row.status === 'draft')
  const ready = reviews.filter((row) => row.status === 'self_submitted')
  return (
    <div className="grid gap-4">
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event: FormEvent<HTMLFormElement>) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send('/api/v1/kpis', { name: String(body.get('name')), unit: String(body.get('unit')), weight: Number(body.get('weight')) }, 'KPI saved.', ['kpis']) }}>
        <h2 className="sm:col-span-2 text-base font-semibold">KPI</h2>
        <Field label="Name" htmlFor="kpi-name"><Input id="kpi-name" name="name" required /></Field>
        <Field label="Unit" htmlFor="kpi-unit"><Input id="kpi-unit" name="unit" required /></Field>
        <Field label="Weight" htmlFor="kpi-weight"><Input id="kpi-weight" name="weight" type="number" min="1" defaultValue="1" required /></Field>
        <Button type="submit" disabled={form.pending}>Save KPI</Button>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send('/api/v1/goals', { scope: String(body.get('scope')), employee_id: body.get('employee_id') ? Number(body.get('employee_id')) : null, title: String(body.get('title')), period: String(body.get('period')), period_on: String(body.get('period_on')), target: String(body.get('target')) }, 'Goal saved.', ['goals']) }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Goal</h2>
        <Field label="Scope" htmlFor="goal-scope"><Select id="goal-scope" name="scope" defaultValue="employee" options={[{ value: 'employee', label: 'Employee' }, { value: 'department', label: 'Department' }]} /></Field>
        <Field label="Employee" htmlFor="goal-employee"><Select id="goal-employee" name="employee_id" placeholder="Optional" options={(employees.data ?? []).map((row) => ({ value: String(row.id), label: row.full_name }))} /></Field>
        <Field label="Title" htmlFor="goal-title"><Input id="goal-title" name="title" required /></Field>
        <Field label="Period" htmlFor="goal-period"><Select id="goal-period" name="period" defaultValue="monthly" options={[{ value: 'monthly', label: 'Monthly' }, { value: 'quarterly', label: 'Quarterly' }, { value: 'annual', label: 'Annual' }]} /></Field>
        <Field label="Period date" htmlFor="goal-date"><Input id="goal-date" name="period_on" type="date" required /></Field>
        <Field label="Target" htmlFor="goal-target"><Input id="goal-target" name="target" required /></Field>
        <Button type="submit" disabled={form.pending}>Save goal</Button>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send('/api/v1/performance-reviews', { employee_id: Number(body.get('employee_id')), cycle: String(body.get('cycle')), period_on: String(body.get('period_on')) }, 'Review opened.', ['performance-reviews']) }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Review</h2>
        <Field label="Employee" htmlFor="review-employee"><Select id="review-employee" name="employee_id" options={(employees.data ?? []).map((row) => ({ value: String(row.id), label: row.full_name }))} /></Field>
        <Field label="Cycle" htmlFor="review-cycle"><Select id="review-cycle" name="cycle" defaultValue="quarterly" options={[{ value: 'monthly', label: 'Monthly target' }, { value: 'quarterly', label: 'Quarterly review' }, { value: 'annual', label: 'Annual appraisal' }]} /></Field>
        <Field label="Period date" htmlFor="review-date"><Input id="review-date" name="period_on" type="date" required /></Field>
        <Button type="submit" disabled={form.pending}>Open review</Button>
      </form>
      {drafts.length > 0 ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send(`/api/v1/performance-reviews/${String(body.get('review_id'))}/self`, { score: Number(body.get('score')), comments: String(body.get('comments')) }, 'Self-assessment saved.', ['performance-reviews']) }}>
          <h2 className="sm:col-span-2 text-base font-semibold">Self-assessment</h2>
          <Field label="Review" htmlFor="self-review"><Select id="self-review" name="review_id" options={drafts.map((row) => ({ value: String(row.id), label: `${row.employee ?? 'Employee'} · ${row.cycle}` }))} /></Field>
          <Field label="Score" htmlFor="self-score"><Input id="self-score" name="score" type="number" min="1" max="5" required /></Field>
          <Field label="Comments" htmlFor="self-comments"><Textarea id="self-comments" name="comments" required /></Field>
          <Button type="submit" disabled={form.pending}>Submit self-assessment</Button>
        </form>
      ) : null}
      {ready.length > 0 ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send(`/api/v1/performance-reviews/${String(body.get('review_id'))}/complete`, { score: Number(body.get('score')), comments: String(body.get('comments')), promotion_recommended: String(body.get('promotion')) === 'yes' }, 'Review completed.', ['performance-reviews']) }}>
          <h2 className="sm:col-span-2 text-base font-semibold">Manager feedback</h2>
          <Field label="Review" htmlFor="complete-review"><Select id="complete-review" name="review_id" options={ready.map((row) => ({ value: String(row.id), label: `${row.employee ?? 'Employee'} · ${row.cycle}` }))} /></Field>
          <Field label="Score" htmlFor="manager-score"><Input id="manager-score" name="score" type="number" min="1" max="5" required /></Field>
          <Field label="Promotion" htmlFor="promotion"><Select id="promotion" name="promotion" defaultValue="no" options={[{ value: 'no', label: 'Not recommended' }, { value: 'yes', label: 'Recommend promotion' }]} /></Field>
          <Field label="Comments" htmlFor="manager-comments"><Textarea id="manager-comments" name="comments" required /></Field>
          <Button type="submit" disabled={form.pending}>Complete review</Button>
        </form>
      ) : null}
      {form.error ? <p className="text-sm text-destructive">{form.error}</p> : null}
    </div>
  )
}

type AssetRow = { id: number; asset_code: string; category: string; name: string; condition: string; status: string; allocation_id: number | null }

export function AssetsPage() {
  const { organizationId, permissions } = useSession()
  const assets = useQuery({ queryKey: ['assets', organizationId], enabled: organizationId !== null, queryFn: () => apiData<AssetRow[]>('/api/v1/assets') })
  if (organizationId === null) return <EmptyState title="Select a business" description="Assets belong to the active business." />
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Assets" description="Inventory, allocation, return, condition, and maintenance." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Assets' }]} />
      {assets.isPending ? <Skeleton className="h-20 w-full" /> : assets.isError ? <ErrorState title="Assets could not be loaded" description={assets.error instanceof ApiError ? assets.error.message : 'Try again.'} /> : assets.data.length === 0 ? <EmptyState title="No assets" description="Add a laptop, mobile, ID card, or accessory." /> : (
        <DataTable caption="Inventory" rows={assets.data} getRowId={(row) => String(row.id)} columns={[
          { id: 'code', header: 'Code', cell: (row) => row.asset_code },
          { id: 'name', header: 'Name', cell: (row) => row.name },
          { id: 'category', header: 'Category', cell: (row) => row.category },
          { id: 'condition', header: 'Condition', cell: (row) => row.condition },
          { id: 'status', header: 'Status', cell: (row) => row.status },
        ]} />
      )}
      {permissions.has('assets.manage') ? <AssetForm assets={assets.data ?? []} /> : null}
    </div>
  )
}

function AssetForm({ assets }: { assets: AssetRow[] }) {
  const { organizationId } = useSession()
  const form = useOps()
  const employees = useQuery({ queryKey: ['employees', organizationId, 'assets'], enabled: organizationId !== null, queryFn: () => apiData<EmployeeRow[]>('/api/v1/employees') })
  const stock = assets.filter((row) => row.status === 'in_stock')
  const assigned = assets.filter((row) => row.allocation_id !== null)
  return (
    <div className="grid gap-4">
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send('/api/v1/assets', { asset_code: String(body.get('asset_code')), category: String(body.get('category')), name: String(body.get('name')), condition: String(body.get('condition')) }, 'Asset added to inventory.', ['assets']) }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Inventory</h2>
        <Field label="Code" htmlFor="asset-code"><Input id="asset-code" name="asset_code" required /></Field>
        <Field label="Name" htmlFor="asset-name"><Input id="asset-name" name="name" required /></Field>
        <Field label="Category" htmlFor="asset-category"><Select id="asset-category" name="category" defaultValue="laptop" options={[{ value: 'laptop', label: 'Laptop' }, { value: 'mobile', label: 'Mobile' }, { value: 'id_card', label: 'ID card' }, { value: 'accessory', label: 'Accessory' }, { value: 'other', label: 'Other' }]} /></Field>
        <Field label="Condition" htmlFor="asset-condition"><Select id="asset-condition" name="condition" defaultValue="good" options={[{ value: 'good', label: 'Good' }, { value: 'fair', label: 'Fair' }, { value: 'damaged', label: 'Damaged' }]} /></Field>
        <Button type="submit" disabled={form.pending}>Add asset</Button>
      </form>
      {stock.length > 0 ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send(`/api/v1/assets/${String(body.get('asset_id'))}/assign`, { employee_id: Number(body.get('employee_id')), allocated_on: String(body.get('allocated_on')) }, 'Asset allocated.', ['assets', 'employee-assets']) }}>
          <h2 className="sm:col-span-2 text-base font-semibold">Allocate</h2>
          <Field label="Asset" htmlFor="assign-asset"><Select id="assign-asset" name="asset_id" options={stock.map((row) => ({ value: String(row.id), label: `${row.asset_code} · ${row.name}` }))} /></Field>
          <Field label="Employee" htmlFor="assign-employee"><Select id="assign-employee" name="employee_id" options={(employees.data ?? []).map((row) => ({ value: String(row.id), label: row.full_name }))} /></Field>
          <Field label="Allocated on" htmlFor="assign-date"><Input id="assign-date" name="allocated_on" type="date" required /></Field>
          <Button type="submit" disabled={form.pending}>Assign asset</Button>
        </form>
      ) : null}
      {assigned.length > 0 ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send(`/api/v1/asset-allocations/${String(body.get('allocation_id'))}/return`, { returned_on: String(body.get('returned_on')), condition: String(body.get('condition')) }, 'Asset returned.', ['assets', 'employee-assets']) }}>
          <h2 className="sm:col-span-2 text-base font-semibold">Return</h2>
          <Field label="Allocation" htmlFor="return-allocation"><Select id="return-allocation" name="allocation_id" options={assigned.map((row) => ({ value: String(row.allocation_id), label: `${row.asset_code} · ${row.name}` }))} /></Field>
          <Field label="Condition" htmlFor="return-condition"><Select id="return-condition" name="condition" defaultValue="good" options={[{ value: 'good', label: 'Good' }, { value: 'fair', label: 'Fair' }, { value: 'damaged', label: 'Damaged' }]} /></Field>
          <Field label="Returned on" htmlFor="return-date"><Input id="return-date" name="returned_on" type="date" required /></Field>
          <Button type="submit" disabled={form.pending}>Record return</Button>
        </form>
      ) : null}
      {assets.length > 0 ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send(`/api/v1/assets/${String(body.get('asset_id'))}/maintenance`, { performed_on: String(body.get('performed_on')), notes: String(body.get('notes')) }, 'Maintenance recorded.', ['assets']) }}>
          <h2 className="sm:col-span-2 text-base font-semibold">Maintenance</h2>
          <Field label="Asset" htmlFor="maintain-asset"><Select id="maintain-asset" name="asset_id" options={assets.map((row) => ({ value: String(row.id), label: `${row.asset_code} · ${row.name}` }))} /></Field>
          <Field label="Performed on" htmlFor="maintain-date"><Input id="maintain-date" name="performed_on" type="date" required /></Field>
          <Field label="Notes" htmlFor="maintain-notes"><Input id="maintain-notes" name="notes" required /></Field>
          <Button type="submit" disabled={form.pending}>Record maintenance</Button>
        </form>
      ) : null}
      {form.error ? <p className="text-sm text-destructive">{form.error}</p> : null}
    </div>
  )
}

export function ExpensesPage() {
  const { organizationId } = useSession()
  const [search] = useSearchParams()
  const focus = search.get('view')
  const claims = useQuery({ queryKey: ['expenses', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; employee: string | null; category: string | null; amount: string; status: string }>>('/api/v1/expenses') })
  useEffect(() => {
    const target = focus === 'claims' ? 'expense-claims' : focus === 'submit' ? 'expense-submit' : null
    if (target) document.getElementById(target)?.scrollIntoView({ block: 'start' })
  }, [focus, claims.data])
  if (organizationId === null) return <EmptyState title="Select a business" description="Expenses belong to the active business." />
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Expenses" description="Submit a claim, then a manager and a different finance or HR approver. A rejected claim can be resubmitted." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Expenses' }]} />
      {claims.isPending ? <Skeleton className="h-20 w-full" /> : claims.isError ? <ErrorState title="Expenses could not be loaded" description={claims.error instanceof ApiError ? claims.error.message : 'Try again.'} /> : claims.data.length === 0 ? <EmptyState title="No expenses" description="Claims appear after an employee submits one." /> : (
        <div id="expense-claims"><DataTable caption="Claims" rows={claims.data} getRowId={(row) => String(row.id)} columns={[
          { id: 'employee', header: 'Employee', cell: (row) => row.employee ?? '' },
          { id: 'category', header: 'Category', cell: (row) => row.category ?? '' },
          { id: 'amount', header: 'Amount', cell: (row) => row.amount },
          { id: 'status', header: 'Status', cell: (row) => row.status },
        ]} /></div>
      )}
      <div id="expense-submit"><ExpenseForm claims={claims.data ?? []} /></div>
    </div>
  )
}

function ExpenseForm({ claims }: { claims: Array<{ id: number; employee: string | null; status: string }> }) {
  const { organizationId, permissions } = useSession()
  const form = useOps()
  const categories = useQuery({ queryKey: ['expense-categories', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Named[]>('/api/v1/expense-categories') })
  const pendingClaims = claims.filter((row) => row.status === 'submitted' || row.status === 'manager_approved')
  const payable = claims.filter((row) => row.status === 'approved')
  const rejected = claims.filter((row) => row.status === 'rejected')
  return (
    <div className="grid gap-4">
      {permissions.has('expenses.approve') ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); void form.send('/api/v1/expense-categories', { name: String(new FormData(event.currentTarget).get('name') ?? '') }, 'Category saved.', ['expense-categories']) }}>
          <Field label="Category" htmlFor="expense-category-name"><Input id="expense-category-name" name="name" required /></Field>
          <div className="flex items-end"><Button type="submit" disabled={form.pending}>Save category</Button></div>
        </form>
      ) : null}
      {permissions.has('expenses.submit') ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send('/api/v1/expenses', { expense_category_id: Number(body.get('expense_category_id')), amount: Number(body.get('amount')), incurred_on: String(body.get('incurred_on')), description: String(body.get('description')) }, 'Expense submitted.', ['expenses']) }}>
          <Field label="Category" htmlFor="claim-category"><Select id="claim-category" name="expense_category_id" options={(categories.data ?? []).map((row) => ({ value: String(row.id), label: row.name }))} /></Field>
          <Field label="Amount" htmlFor="claim-amount"><Input id="claim-amount" name="amount" type="number" min="0" step="0.01" required /></Field>
          <Field label="Incurred on" htmlFor="claim-date"><Input id="claim-date" name="incurred_on" type="date" required /></Field>
          <Field label="Description" htmlFor="claim-description"><Input id="claim-description" name="description" required /></Field>
          <Button type="submit" disabled={form.pending}>Submit expense</Button>
        </form>
      ) : null}
      {permissions.has('expenses.approve') && pendingClaims.length > 0 ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); const id = String(body.get('claim_id')); const action = String(body.get('action')); const path = action === 'reject' ? `/api/v1/expenses/${id}/reject` : `/api/v1/expenses/${id}/${action}`; void form.send(path, action === 'reject' ? { reason: String(body.get('reason')) } : null, 'Expense updated.', ['expenses']) }}>
          <h2 className="sm:col-span-2 text-base font-semibold">Approval</h2>
          <Field label="Claim" htmlFor="claim-id"><Select id="claim-id" name="claim_id" options={pendingClaims.map((row) => ({ value: String(row.id), label: `${row.employee ?? 'Employee'} · ${row.status}` }))} /></Field>
          <Field label="Action" htmlFor="claim-action"><Select id="claim-action" name="action" defaultValue="manager-approval" options={[{ value: 'manager-approval', label: 'Manager approval' }, { value: 'finance-approval', label: 'HR or finance approval' }, { value: 'reject', label: 'Reject' }]} /></Field>
          <Field label="Rejection reason" htmlFor="claim-reason"><Input id="claim-reason" name="reason" /></Field>
          <Button type="submit" disabled={form.pending}>Update claim</Button>
        </form>
      ) : null}
      {permissions.has('expenses.approve') && payable.length > 0 ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); void form.send(`/api/v1/expenses/${String(new FormData(event.currentTarget).get('claim_id'))}/reimburse`, null, 'Reimbursement recorded.', ['expenses']) }}>
          <Field label="Approved claim" htmlFor="reimburse-claim"><Select id="reimburse-claim" name="claim_id" options={payable.map((row) => ({ value: String(row.id), label: row.employee ?? 'Employee' }))} /></Field>
          <div className="flex items-end"><Button type="submit" disabled={form.pending}>Reimburse</Button></div>
        </form>
      ) : null}
      {permissions.has('expenses.submit') && rejected.length > 0 ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); void form.send(`/api/v1/expenses/${String(new FormData(event.currentTarget).get('claim_id'))}/resubmit`, null, 'Claim resubmitted.', ['expenses']) }}>
          <Field label="Rejected claim" htmlFor="resubmit-claim"><Select id="resubmit-claim" name="claim_id" options={rejected.map((row) => ({ value: String(row.id), label: row.employee ?? 'Employee' }))} /></Field>
          <div className="flex items-end"><Button type="submit" disabled={form.pending}>Resubmit</Button></div>
        </form>
      ) : null}
      {form.error ? <p className="text-sm text-destructive">{form.error}</p> : null}
    </div>
  )
}

export function HelpdeskPage() {
  const { organizationId, permissions } = useSession()
  const tickets = useQuery({ queryKey: ['tickets', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; subject: string; priority: string; status: string; due_at: string | null }>>('/api/v1/tickets') })
  if (organizationId === null) return <EmptyState title="Select a business" description="Helpdesk tickets belong to the active business." />
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Helpdesk" description="Queries, categories, assignment, priority, and the SLA due time." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Helpdesk' }]} />
      {tickets.isPending ? <Skeleton className="h-20 w-full" /> : tickets.isError ? <ErrorState title="Tickets could not be loaded" description={tickets.error instanceof ApiError ? tickets.error.message : 'Try again.'} /> : tickets.data.length === 0 ? <EmptyState title="No tickets" description="Employee queries appear here." /> : (
        <DataTable caption="Tickets" rows={tickets.data} getRowId={(row) => String(row.id)} columns={[
          { id: 'subject', header: 'Subject', cell: (row) => row.subject },
          { id: 'priority', header: 'Priority', cell: (row) => row.priority },
          { id: 'status', header: 'Status', cell: (row) => row.status },
          { id: 'due', header: 'Due', cell: (row) => row.due_at ?? 'No SLA' },
        ]} />
      )}
      {permissions.has('helpdesk.manage') ? <HelpdeskForms tickets={tickets.data ?? []} /> : null}
    </div>
  )
}

function HelpdeskForms({ tickets }: { tickets: Array<{ id: number; subject: string; status: string }> }) {
  const { account, organizationId } = useSession()
  const form = useOps()
  const categories = useQuery({ queryKey: ['ticket-categories', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Named[]>('/api/v1/ticket-categories') })
  const employees = useQuery({ queryKey: ['employees', organizationId, 'helpdesk'], enabled: organizationId !== null, queryFn: () => apiData<EmployeeRow[]>('/api/v1/employees') })
  const openTickets = tickets.filter((row) => row.status !== 'resolved')
  return (
    <div className="grid gap-4">
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); void form.send('/api/v1/ticket-categories', { name: String(new FormData(event.currentTarget).get('name') ?? '') }, 'Ticket category saved.', ['ticket-categories']) }}>
        <Field label="Category" htmlFor="ticket-category"><Input id="ticket-category" name="name" required /></Field>
        <div className="flex items-end"><Button type="submit" disabled={form.pending}>Save category</Button></div>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send('/api/v1/sla-policies', { priority: String(body.get('priority')), response_hours: Number(body.get('response_hours')) }, 'SLA saved.', ['tickets']) }}>
        <Field label="Priority" htmlFor="sla-priority"><Select id="sla-priority" name="priority" defaultValue="high" options={['low', 'medium', 'high', 'urgent'].map((value) => ({ value, label: value }))} /></Field>
        <Field label="Response hours" htmlFor="sla-hours"><Input id="sla-hours" name="response_hours" type="number" min="1" required /></Field>
        <Button type="submit" disabled={form.pending}>Save SLA</Button>
      </form>
      {(categories.data ?? []).length > 0 ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send('/api/v1/tickets', { employee_id: Number(body.get('employee_id')), ticket_category_id: Number(body.get('ticket_category_id')), subject: String(body.get('subject')), priority: String(body.get('priority')) }, 'Ticket opened.', ['tickets']) }}>
          <h2 className="sm:col-span-2 text-base font-semibold">Query</h2>
          <Field label="Employee" htmlFor="ticket-employee"><Select id="ticket-employee" name="employee_id" options={(employees.data ?? []).map((row) => ({ value: String(row.id), label: row.full_name }))} /></Field>
          <Field label="Category" htmlFor="ticket-category-id"><Select id="ticket-category-id" name="ticket_category_id" options={(categories.data ?? []).map((row) => ({ value: String(row.id), label: row.name }))} /></Field>
          <Field label="Subject" htmlFor="ticket-subject"><Input id="ticket-subject" name="subject" required /></Field>
          <Field label="Priority" htmlFor="ticket-priority"><Select id="ticket-priority" name="priority" defaultValue="medium" options={['low', 'medium', 'high', 'urgent'].map((value) => ({ value, label: value }))} /></Field>
          <Button type="submit" disabled={form.pending}>Open ticket</Button>
        </form>
      ) : null}
      {openTickets.length > 0 && account ? (
        <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); const id = String(body.get('ticket_id')); const action = String(body.get('action')); if (action === 'comment') { void form.send(`/api/v1/tickets/${id}/comments`, { body: String(body.get('body')) }, 'Comment added.', ['tickets']); return } if (action === 'resolve') { void form.send(`/api/v1/tickets/${id}/resolve`, null, 'Ticket resolved.', ['tickets']); return } void form.send(`/api/v1/tickets/${id}/assign`, { assignee_user_id: account.id }, 'Ticket assigned.', ['tickets']) }}>
          <h2 className="sm:col-span-2 text-base font-semibold">Follow-up</h2>
          <Field label="Ticket" htmlFor="ticket-id"><Select id="ticket-id" name="ticket_id" options={openTickets.map((row) => ({ value: String(row.id), label: row.subject }))} /></Field>
          <Field label="Action" htmlFor="ticket-action"><Select id="ticket-action" name="action" defaultValue="assign" options={[{ value: 'assign', label: 'Assign to me' }, { value: 'comment', label: 'Add comment' }, { value: 'resolve', label: 'Resolve' }]} /></Field>
          <Field label="Comment" htmlFor="ticket-comment"><Input id="ticket-comment" name="body" /></Field>
          <Button type="submit" disabled={form.pending}>Update ticket</Button>
        </form>
      ) : null}
      {form.error ? <p className="text-sm text-destructive">{form.error}</p> : null}
    </div>
  )
}

export function EngagementPage() {
  const { organizationId, permissions } = useSession()
  const [search] = useSearchParams()
  const focus = search.get('view')
  const posts = useQuery({ queryKey: ['posts', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; kind: string; title: string; body: string }>>('/api/v1/posts') })
  const surveys = useQuery({ queryKey: ['surveys', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; title: string; question: string }>>('/api/v1/surveys') })
  const recognitions = useQuery({ queryKey: ['recognitions', organizationId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ employee: string | null; message: string; awarded_on: string | null }>>('/api/v1/recognitions') })
  const reminders = useQuery({ queryKey: ['reminders', organizationId], enabled: organizationId !== null, queryFn: () => apiData<{ birthdays: Array<{ name: string; day: string }>; anniversaries: Array<{ name: string; years: number }> }>('/api/v1/reminders') })
  useEffect(() => {
    if (focus === 'recognition') document.getElementById('engagement-recognition')?.scrollIntoView({ block: 'start' })
  }, [focus, recognitions.data])
  if (organizationId === null) return <EmptyState title="Select a business" description="Announcements belong to the active business." />
  return (
    <div className="flex flex-col gap-6">
      <PageHeader title="Engagement" description="Announcements, news, events, birthdays, anniversaries, feedback, surveys, and recognition." crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Engagement' }]} />
      {reminders.data ? (
        <div className="text-sm">
          <p>Birthdays this month: {reminders.data.birthdays.map((row) => `${row.name} (${row.day})`).join(', ') || 'None recorded'}</p>
          <p>Work anniversaries: {reminders.data.anniversaries.map((row) => `${row.name} (${row.years} years)`).join(', ') || 'None this month'}</p>
        </div>
      ) : null}
      {posts.isPending ? <Skeleton className="h-20 w-full" /> : posts.data?.length === 0 ? <EmptyState title="No posts" description="Publish an announcement, news item, or event." /> : (
        <ul className="text-sm">{posts.data?.map((post) => <li key={post.id}><span className="font-medium">{post.title}</span> · {post.kind} — {post.body}</li>)}</ul>
      )}
      <div id="engagement-recognition">
        {recognitions.data && recognitions.data.length > 0 ? <ul className="text-sm">{recognitions.data.map((row) => <li key={`${row.employee}-${row.awarded_on}`}>{row.employee} · {row.message}</li>)}</ul> : <EmptyState title="No recognition yet" description="Kudos appear here after they are saved for an employee." />}
      </div>
      {surveys.data && surveys.data.length > 0 ? <ul className="text-sm">{surveys.data.map((row) => <li key={row.id}><span className="font-medium">{row.title}</span> — {row.question}</li>)}</ul> : null}
      {permissions.has('engagement.manage') ? <EngagementForm /> : null}
    </div>
  )
}

function EngagementForm() {
  const { organizationId } = useSession()
  const form = useOps()
  const employees = useQuery({ queryKey: ['employees', organizationId, 'engagement'], enabled: organizationId !== null, queryFn: () => apiData<EmployeeRow[]>('/api/v1/employees') })
  return (
    <div className="grid gap-4">
      <form className="grid gap-3" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send('/api/v1/posts', { kind: String(body.get('kind')), title: String(body.get('title')), body: String(body.get('body')), occurs_on: String(body.get('occurs_on') ?? '') || null }, 'Post published.', ['posts']) }}>
        <Field label="Kind" htmlFor="post-kind"><Select id="post-kind" name="kind" defaultValue="announcement" options={[{ value: 'announcement', label: 'Announcement' }, { value: 'news', label: 'News' }, { value: 'event', label: 'Event' }]} /></Field>
        <Field label="Title" htmlFor="post-title"><Input id="post-title" name="title" required /></Field>
        <Field label="Body" htmlFor="post-body"><Textarea id="post-body" name="body" required /></Field>
        <Field label="Date" htmlFor="post-date"><Input id="post-date" name="occurs_on" type="date" /></Field>
        <Button type="submit" disabled={form.pending}>Publish</Button>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send('/api/v1/surveys', { title: String(body.get('title')), question: String(body.get('question')) }, 'Survey saved.', ['surveys']) }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Survey</h2>
        <Field label="Title" htmlFor="survey-title"><Input id="survey-title" name="title" required /></Field>
        <Field label="Question" htmlFor="survey-question"><Input id="survey-question" name="question" required /></Field>
        <Button type="submit" disabled={form.pending}>Save survey</Button>
      </form>
      <form className="grid gap-3 sm:grid-cols-2" onSubmit={(event) => { event.preventDefault(); const body = new FormData(event.currentTarget); void form.send('/api/v1/recognitions', { employee_id: Number(body.get('employee_id')), message: String(body.get('message')), awarded_on: String(body.get('awarded_on')) }, 'Recognition saved.', ['recognitions']) }}>
        <h2 className="sm:col-span-2 text-base font-semibold">Recognition</h2>
        <Field label="Employee" htmlFor="recognition-employee"><Select id="recognition-employee" name="employee_id" options={(employees.data ?? []).map((row) => ({ value: String(row.id), label: row.full_name }))} /></Field>
        <Field label="Awarded on" htmlFor="recognition-date"><Input id="recognition-date" name="awarded_on" type="date" required /></Field>
        <Field label="Message" htmlFor="recognition-message"><Input id="recognition-message" name="message" required /></Field>
        <Button type="submit" disabled={form.pending}>Recognize</Button>
      </form>
      {form.error ? <p className="text-sm text-destructive">{form.error}</p> : null}
    </div>
  )
}

export function EmployeeAssets({ employeeId }: { employeeId: number }) {
  const { organizationId } = useSession()
  const rows = useQuery({ queryKey: ['employee-assets', organizationId, employeeId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ asset_code: string | null; name: string | null; allocated_on: string | null; returned_on: string | null; return_condition: string | null }>>(`/api/v1/employees/${employeeId}/assets`) })
  if (rows.isPending) return <Skeleton className="h-16 w-full" />
  if (rows.isError) return <ErrorState title="Assets could not be loaded" description={rows.error instanceof ApiError ? rows.error.message : 'Try again.'} />
  if (rows.data.length === 0) return <EmptyState title="No assets" description="Allocated laptops, mobiles, ID cards, and accessories appear here." />
  return <ul className="text-sm">{rows.data.map((row) => <li key={`${row.asset_code}-${row.allocated_on}`}>{row.asset_code} {row.name} · {row.allocated_on}{row.returned_on ? ` returned ${row.returned_on} (${row.return_condition})` : ''}</li>)}</ul>
}

export function EmployeePerformance({ employeeId }: { employeeId: number }) {
  const { organizationId } = useSession()
  const rows = useQuery({ queryKey: ['performance-reviews', organizationId, employeeId], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; employee_id: number; employee: string | null; cycle: string; status: string; manager_score: number | null }>>('/api/v1/performance-reviews') })
  if (rows.isPending) return <Skeleton className="h-16 w-full" />
  if (rows.isError) return <ErrorState title="Performance could not be loaded" description={rows.error instanceof ApiError ? rows.error.message : 'Try again.'} />
  const mine = rows.data.filter((row) => row.employee_id === employeeId)
  if (mine.length === 0) return <EmptyState title="No reviews" description="Review history appears after a cycle is opened." />
  return <ul className="text-sm">{mine.map((row) => <li key={row.id}>{row.cycle} · {row.status}{row.manager_score === null ? '' : ` · score ${row.manager_score}`}</li>)}</ul>
}

export function EmployeeExpenses() {
  const { organizationId } = useSession()
  const rows = useQuery({ queryKey: ['expenses', organizationId, 'profile'], enabled: organizationId !== null, queryFn: () => apiData<Array<{ id: number; amount: string; status: string; category: string | null }>>('/api/v1/expenses') })
  if (rows.isPending) return <Skeleton className="h-16 w-full" />
  if (rows.isError) return <ErrorState title="Expenses could not be loaded" description={rows.error instanceof ApiError ? rows.error.message : 'Try again.'} />
  if (rows.data.length === 0) return <EmptyState title="No expenses" description="Submitted claims appear here." />
  return <ul className="text-sm">{rows.data.map((row) => <li key={row.id}>{row.category} · {row.amount} · {row.status}</li>)}</ul>
}
