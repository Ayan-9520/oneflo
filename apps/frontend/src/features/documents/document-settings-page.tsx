import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState, type FormEvent } from 'react'

import { useSession } from '@/app/session'
import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/ui/empty-state'
import { ErrorState } from '@/components/ui/error-state'
import { Field, Input, Label, Textarea } from '@/components/ui/input'
import { PageHeader } from '@/components/ui/page-header'
import { Select } from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { Tabs } from '@/components/ui/tabs'
import { useToast } from '@/components/ui/toast'
import { ApiError, apiData, apiFetch } from '@/lib/api'

type LegalEntity = { id: number; brand_id: number | null; legal_name: string }
type Profile = {
  id: number
  legal_company_name: string
  brand_name: string
  registered_address: string | null
  cin: string | null
  pan: string | null
  tan: string | null
  gstin: string | null
  contact_email: string | null
  contact_phone: string | null
  website: string | null
  has_stamp: boolean
  payroll_pf_number: string | null
  payroll_esi_number: string | null
  payroll_pt_number: string | null
  payroll_lin: string | null
  offices: Array<{ id: number; label: string; line_1: string; city: string; state: string }>
  signatories: Array<{ id: number; name: string; designation: string; is_primary: boolean; has_signature: boolean }>
}
type Identity = {
  brand_id: number
  primary_color: string
  secondary_color: string
  header_text: string | null
  footer_text: string | null
  assets: Record<string, boolean>
}
type DocumentType = { key: string; label: string; official: boolean }
type TemplateVersion = { version: number; body: string; footer_note: string | null }
type Template = { id: number; document_type: string; name: string; current_version: TemplateVersion | null }
type Preview = { html: string; official: boolean; oneflo_attribution: boolean }
type PlatformBranding = { powered_by_text: string; website_url: string | null; show_footer: boolean; has_logo: boolean }

const emptyProfile = {
  legal_company_name: '',
  brand_name: '',
  registered_address: '',
  cin: '',
  pan: '',
  tan: '',
  gstin: '',
  contact_email: '',
  contact_phone: '',
  website: '',
  payroll_pf_number: '',
  payroll_esi_number: '',
  payroll_pt_number: '',
  payroll_lin: '',
}

export function DocumentSettingsPage() {
  const { organizationId, permissions, account } = useSession()
  const entities = useQuery({
    queryKey: ['legal-entities', organizationId],
    enabled: organizationId !== null,
    queryFn: () => apiData<LegalEntity[]>('/api/v1/legal-entities'),
  })
  const [legalEntityId, setLegalEntityId] = useState<number | null>(null)
  const selected = entities.data?.find((entity) => entity.id === legalEntityId) ?? entities.data?.[0] ?? null

  useEffect(() => {
    if (selected && legalEntityId !== selected.id) {
      setLegalEntityId(selected.id)
    }
  }, [selected, legalEntityId])

  if (organizationId === null) {
    return <EmptyState title="Select a business" description="Document settings belong to the active business." />
  }
  if (entities.isPending) {
    return <Skeleton className="h-40 w-full" />
  }
  if (entities.isError) {
    return <ErrorState title="Legal entities could not be loaded" description={message(entities.error)} />
  }
  if (!selected) {
    return <EmptyState title="No legal entity yet" description="Add a legal entity before configuring employer documents." />
  }

  return (
    <div className="mx-auto flex max-w-5xl flex-col gap-8">
      <PageHeader
        title="Document settings"
        description="Employer identity, branding, and templates for the selected legal entity."
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Documents' }]}
      />
      <div className="max-w-md">
        <Label htmlFor="legal-entity">Legal entity</Label>
        <Select
          id="legal-entity"
          className="mt-1.5"
          value={String(selected.id)}
          options={entities.data.map((entity) => ({ value: String(entity.id), label: entity.legal_name }))}
          onChange={(event) => setLegalEntityId(Number(event.target.value))}
        />
      </div>
      <Tabs
        tabs={[
          {
            id: 'profile',
            label: 'Company profile',
            content: <ProfilePanel entity={selected} canEdit={permissions.has('organization.manage')} canBrand={permissions.has('branding.manage')} />,
          },
          {
            id: 'branding',
            label: 'Branding',
            content: (
              <BrandingPanel
                entity={selected}
                canEdit={permissions.has('branding.manage')}
                canManagePlatform={account?.platform_permissions.includes('branding.manage') ?? false}
              />
            ),
          },
          {
            id: 'templates',
            label: 'Templates',
            content: <TemplatePanel entity={selected} canGenerate={permissions.has('documents.generate')} canPreview={permissions.has('documents.view') || permissions.has('documents.generate')} />,
          },
        ]}
      />
    </div>
  )
}

function ProfilePanel({ entity, canEdit, canBrand }: { entity: LegalEntity; canEdit: boolean; canBrand: boolean }) {
  const { organizationId } = useSession()
  const { notify } = useToast()
  const queryClient = useQueryClient()
  const profile = useQuery({
    queryKey: ['company-profile', organizationId, entity.id],
    queryFn: () => apiData<Profile | null>(`/api/v1/legal-entities/${entity.id}/company-profile`),
  })
  const [form, setForm] = useState(emptyProfile)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!profile.data) {
      setForm({ ...emptyProfile, legal_company_name: entity.legal_name, brand_name: entity.legal_name })
      return
    }
    setForm({
      legal_company_name: profile.data.legal_company_name,
      brand_name: profile.data.brand_name,
      registered_address: profile.data.registered_address ?? '',
      cin: profile.data.cin ?? '',
      pan: profile.data.pan ?? '',
      tan: profile.data.tan ?? '',
      gstin: profile.data.gstin ?? '',
      contact_email: profile.data.contact_email ?? '',
      contact_phone: profile.data.contact_phone ?? '',
      website: profile.data.website ?? '',
      payroll_pf_number: profile.data.payroll_pf_number ?? '',
      payroll_esi_number: profile.data.payroll_esi_number ?? '',
      payroll_pt_number: profile.data.payroll_pt_number ?? '',
      payroll_lin: profile.data.payroll_lin ?? '',
    })
  }, [profile.data, entity.id, entity.legal_name])

  const save = useMutation({
    mutationFn: () =>
      apiData<Profile>(`/api/v1/legal-entities/${entity.id}/company-profile`, {
        method: 'PUT',
        body: JSON.stringify(blankToNull(form)),
      }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['company-profile', organizationId, entity.id] })
      setError(null)
      notify('Company profile saved.', 'success')
    },
    onError: (caught) => setError(message(caught)),
  })

  if (profile.isPending) {
    return <Skeleton className="h-40 w-full" />
  }
  if (profile.isError) {
    return <ErrorState title="Company profile could not be loaded" description={message(profile.error)} />
  }

  return (
    <form className="grid gap-4 md:grid-cols-2" onSubmit={(event) => { event.preventDefault(); save.mutate() }}>
      <TextField label="Legal company name" value={form.legal_company_name} onChange={(value) => setForm({ ...form, legal_company_name: value })} />
      <TextField label="Brand name" value={form.brand_name} onChange={(value) => setForm({ ...form, brand_name: value })} />
      <div className="md:col-span-2">
        <Field label="Registered address" htmlFor="registered-address">
          <Textarea id="registered-address" value={form.registered_address} onChange={(event) => setForm({ ...form, registered_address: event.target.value })} />
        </Field>
      </div>
      <TextField label="CIN" value={form.cin} onChange={(value) => setForm({ ...form, cin: value })} />
      <TextField label="PAN" value={form.pan} onChange={(value) => setForm({ ...form, pan: value })} />
      <TextField label="TAN" value={form.tan} onChange={(value) => setForm({ ...form, tan: value })} />
      <TextField label="GSTIN" value={form.gstin} onChange={(value) => setForm({ ...form, gstin: value })} />
      <TextField label="Contact email" value={form.contact_email} onChange={(value) => setForm({ ...form, contact_email: value })} />
      <TextField label="Contact phone" value={form.contact_phone} onChange={(value) => setForm({ ...form, contact_phone: value })} />
      <TextField label="Website" value={form.website} onChange={(value) => setForm({ ...form, website: value })} />
      <TextField label="PF registration" value={form.payroll_pf_number} onChange={(value) => setForm({ ...form, payroll_pf_number: value })} />
      <TextField label="ESI registration" value={form.payroll_esi_number} onChange={(value) => setForm({ ...form, payroll_esi_number: value })} />
      <TextField label="Professional tax registration" value={form.payroll_pt_number} onChange={(value) => setForm({ ...form, payroll_pt_number: value })} />
      <TextField label="LIN" value={form.payroll_lin} onChange={(value) => setForm({ ...form, payroll_lin: value })} />
      {error ? <p className="text-sm text-destructive md:col-span-2">{error}</p> : null}
      <div className="flex w-fit flex-wrap gap-3 md:col-span-2">
        <Button type="submit" disabled={!canEdit || save.isPending}>{save.isPending ? 'Saving…' : 'Save profile'}</Button>
        {profile.data && canBrand ? <FileButton label="Upload company stamp" path={`/api/v1/company-profiles/${profile.data.id}/stamp`} /> : null}
      </div>
      <OfficeList profile={profile.data} canEdit={canEdit} entityId={entity.id} />
      <SignatoryList profile={profile.data} canEdit={canEdit} canBrand={canBrand} entityId={entity.id} />
    </form>
  )
}

function OfficeList({ profile, canEdit, entityId }: { profile: Profile | null; canEdit: boolean; entityId: number }) {
  const { organizationId } = useSession()
  const { notify } = useToast()
  const queryClient = useQueryClient()
  const [label, setLabel] = useState('Office')
  const [line, setLine] = useState('')
  const [city, setCity] = useState('')
  const [state, setState] = useState('')
  const [postal, setPostal] = useState('')
  const [country, setCountry] = useState('')
  const [error, setError] = useState<string | null>(null)

  const add = useMutation({
    mutationFn: () => {
      if (!profile) {
        throw new ApiError('Save the company profile before adding an office.', 422)
      }
      return apiData(`/api/v1/company-profiles/${profile.id}/offices`, {
        method: 'POST',
        body: JSON.stringify({ label, line_1: line, city, state, postal_code: postal, country }),
      })
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['company-profile', organizationId, entityId] })
      setError(null)
      notify('Office address added.', 'success')
    },
    onError: (caught) => setError(message(caught)),
  })

  return (
    <section className="flex flex-col gap-3 border-t border-border pt-6 md:col-span-2">
      <h2 className="text-lg font-semibold">Office addresses</h2>
      {profile && profile.offices.length > 0 ? (
        <ul className="mt-3 space-y-2 text-sm">
          {profile.offices.map((office) => (
            <li key={office.id}>{office.label}: {office.line_1}, {office.city}, {office.state}</li>
          ))}
        </ul>
      ) : (
        <p className="mt-2 text-sm text-muted-foreground">No office addresses yet.</p>
      )}
      <div className="mt-4 grid gap-3 md:grid-cols-2">
        <TextField label="Label" value={label} onChange={setLabel} />
        <TextField label="Address line" value={line} onChange={setLine} />
        <TextField label="City" value={city} onChange={setCity} />
        <TextField label="State" value={state} onChange={setState} />
        <TextField label="Postal code" value={postal} onChange={setPostal} />
        <TextField label="Country" value={country} onChange={setCountry} />
      </div>
      {error ? <p className="mt-2 text-sm text-destructive">{error}</p> : null}
      <Button className="mt-3 w-fit" type="button" variant="outline" disabled={!canEdit || add.isPending} onClick={() => add.mutate()}>
        {add.isPending ? 'Adding…' : 'Add office'}
      </Button>
    </section>
  )
}

function SignatoryList({ profile, canEdit, canBrand, entityId }: { profile: Profile | null; canEdit: boolean; canBrand: boolean; entityId: number }) {
  const { organizationId } = useSession()
  const { notify } = useToast()
  const queryClient = useQueryClient()
  const [name, setName] = useState('')
  const [designation, setDesignation] = useState('')
  const [error, setError] = useState<string | null>(null)
  const add = useMutation({
    mutationFn: () => {
      if (!profile) {
        throw new ApiError('Save the company profile before adding a signatory.', 422)
      }
      return apiData(`/api/v1/company-profiles/${profile.id}/signatories`, {
        method: 'POST',
        body: JSON.stringify({ name, designation, is_primary: profile.signatories.length === 0 }),
      })
    },
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['company-profile', organizationId, entityId] })
      setError(null)
      notify('Signatory added.', 'success')
    },
    onError: (caught) => setError(message(caught)),
  })

  return (
    <section className="flex flex-col gap-3 border-t border-border pt-6 md:col-span-2">
      <h2 className="text-lg font-semibold">Authorized signatories</h2>
      {profile && profile.signatories.length > 0 ? (
        <ul className="mt-3 space-y-3 text-sm">
          {profile.signatories.map((signatory) => (
            <li key={signatory.id} className="flex flex-wrap items-center justify-between gap-2">
              <span>{signatory.name}, {signatory.designation}{signatory.is_primary ? ' · Primary' : ''}</span>
              {canBrand ? <FileButton label="Upload signature" path={`/api/v1/signatories/${signatory.id}/signature`} /> : null}
            </li>
          ))}
        </ul>
      ) : (
        <p className="mt-2 text-sm text-muted-foreground">No signatories yet.</p>
      )}
      <div className="mt-4 grid gap-3 md:grid-cols-2">
        <TextField label="Name" value={name} onChange={setName} />
        <TextField label="Designation" value={designation} onChange={setDesignation} />
      </div>
      {error ? <p className="mt-2 text-sm text-destructive">{error}</p> : null}
      <Button className="mt-3 w-fit" type="button" variant="outline" disabled={!canEdit || add.isPending} onClick={() => add.mutate()}>
        {add.isPending ? 'Adding…' : 'Add signatory'}
      </Button>
    </section>
  )
}

function BrandingPanel({ entity, canEdit, canManagePlatform }: { entity: LegalEntity; canEdit: boolean; canManagePlatform: boolean }) {
  const { organizationId } = useSession()
  const { notify } = useToast()
  const queryClient = useQueryClient()
  const brandId = entity.brand_id
  const identity = useQuery({
    queryKey: ['brand-identity', organizationId, brandId],
    enabled: brandId !== null,
    queryFn: () => apiData<Identity>(`/api/v1/brands/${brandId}/identity`),
  })
  const platform = useQuery({
    queryKey: ['platform-branding', organizationId],
    queryFn: () => apiData<PlatformBranding>('/api/v1/platform-branding'),
  })
  const [colors, setColors] = useState({ primary_color: '#2A1878', secondary_color: '#FF2D87', header_text: '', footer_text: '' })
  const [platformForm, setPlatformForm] = useState({ powered_by_text: 'Powered by OneFlo', website_url: '', show_footer: true })
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!identity.data) {
      return
    }
    setColors({
      primary_color: identity.data.primary_color,
      secondary_color: identity.data.secondary_color,
      header_text: identity.data.header_text ?? '',
      footer_text: identity.data.footer_text ?? '',
    })
  }, [identity.data])

  useEffect(() => {
    if (!platform.data) {
      return
    }
    setPlatformForm({
      powered_by_text: platform.data.powered_by_text,
      website_url: platform.data.website_url ?? '',
      show_footer: platform.data.show_footer,
    })
  }, [platform.data])

  const save = useMutation({
    mutationFn: () => apiData(`/api/v1/brands/${brandId}/identity`, { method: 'PUT', body: JSON.stringify(blankToNull(colors)) }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['brand-identity', organizationId, brandId] })
      setError(null)
      notify('Brand colors saved.', 'success')
    },
    onError: (caught) => setError(message(caught)),
  })
  const savePlatform = useMutation({
    mutationFn: () => apiData('/api/v1/platform-branding', { method: 'PUT', body: JSON.stringify(blankToNull(platformForm)) }),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: ['platform-branding', organizationId] })
      notify('OneFlo branding saved.', 'success')
    },
    onError: (caught) => setError(message(caught)),
  })

  if (brandId === null) {
    return <EmptyState title="This legal entity has no brand" description="Attach a brand before uploading logos." />
  }

  return (
    <div className="grid gap-6">
      <form className="grid gap-4 md:grid-cols-2" onSubmit={(event) => { event.preventDefault(); save.mutate() }}>
        <TextField label="Primary color" value={colors.primary_color} onChange={(value) => setColors({ ...colors, primary_color: value })} />
        <TextField label="Secondary color" value={colors.secondary_color} onChange={(value) => setColors({ ...colors, secondary_color: value })} />
        <TextField label="Document header" value={colors.header_text} onChange={(value) => setColors({ ...colors, header_text: value })} />
        <TextField label="Document footer" value={colors.footer_text} onChange={(value) => setColors({ ...colors, footer_text: value })} />
        {error ? <p className="text-sm text-destructive md:col-span-2">{error}</p> : null}
        <div className="flex w-fit flex-wrap gap-3 md:col-span-2">
          <Button type="submit" disabled={!canEdit || save.isPending}>{save.isPending ? 'Saving…' : 'Save branding'}</Button>
          {canEdit ? (
            <>
              <FileButton label="Company logo" path={`/api/v1/brands/${brandId}/identity/files`} kind="logo" />
              <FileButton label="Alternate logo" path={`/api/v1/brands/${brandId}/identity/files`} kind="alternate" />
              <FileButton label="Light logo" path={`/api/v1/brands/${brandId}/identity/files`} kind="light" />
              <FileButton label="Dark logo" path={`/api/v1/brands/${brandId}/identity/files`} kind="dark" />
              <FileButton label="Letterhead" path={`/api/v1/brands/${brandId}/identity/files`} kind="letterhead" />
            </>
          ) : null}
        </div>
        {identity.data ? (
          <p className="text-sm text-muted-foreground md:col-span-2">
            Uploaded: {Object.entries(identity.data.assets).filter(([, present]) => present).map(([kind]) => kind).join(', ') || 'none'}
          </p>
        ) : null}
      </form>
      <form className="grid gap-4 border-t border-border pt-6" onSubmit={(event) => { event.preventDefault(); savePlatform.mutate() }}>
        <h2 className="text-lg font-semibold">OneFlo attribution</h2>
        <p className="text-sm text-muted-foreground">Shown on company letters. Official certificates never include it.</p>
        <TextField label="Powered by text" value={platformForm.powered_by_text} onChange={(value) => setPlatformForm({ ...platformForm, powered_by_text: value })} />
        <TextField label="OneFlo website" value={platformForm.website_url} onChange={(value) => setPlatformForm({ ...platformForm, website_url: value })} />
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={platformForm.show_footer} onChange={(event) => setPlatformForm({ ...platformForm, show_footer: event.target.checked })} disabled={!canManagePlatform} />
          Show footer branding
        </label>
        {canManagePlatform ? (
          <div className="flex w-fit flex-wrap gap-3">
            <Button type="submit" disabled={savePlatform.isPending}>{savePlatform.isPending ? 'Saving…' : 'Save OneFlo branding'}</Button>
            <FileButton label="OneFlo logo" path="/api/v1/platform-branding/logo" />
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">Only a platform administrator can change OneFlo branding.</p>
        )}
      </form>
    </div>
  )
}

function TemplatePanel({ entity, canGenerate, canPreview }: { entity: LegalEntity; canGenerate: boolean; canPreview: boolean }) {
  const { organizationId } = useSession()
  const { notify } = useToast()
  const queryClient = useQueryClient()
  const types = useQuery({
    queryKey: ['document-types', organizationId],
    queryFn: () => apiData<{ types: DocumentType[] }>('/api/v1/document-types'),
  })
  const templates = useQuery({
    queryKey: ['document-templates', organizationId, entity.id],
    queryFn: () => apiData<Template[]>(`/api/v1/document-templates?legal_entity_id=${entity.id}`),
  })
  const [documentType, setDocumentType] = useState('offer_letter')
  const [name, setName] = useState('Offer letter')
  const [body, setBody] = useState('Hello {{employee.given_name}} {{employee.family_name}},\n\nThis letter is issued by {{company.legal_name}}.')
  const [footerNote, setFooterNote] = useState('')
  const [preview, setPreview] = useState<Preview | null>(null)
  const [error, setError] = useState<string | null>(null)
  const selectedType = types.data?.types.find((type) => type.key === documentType)

  useEffect(() => {
    const existing = templates.data?.find((template) => template.document_type === documentType)
    if (existing?.current_version) {
      setName(existing.name)
      setBody(existing.current_version.body)
      setFooterNote(existing.current_version.footer_note ?? '')
    }
  }, [documentType, templates.data])

  const payload = {
    legal_entity_id: entity.id,
    document_type: documentType,
    name,
    body,
    footer_note: footerNote || null,
    show_logo: true,
    show_signatory: true,
    show_stamp: false,
  }
  const previewMutation = useMutation({
    mutationFn: () => apiData<Preview>('/api/v1/document-previews', { method: 'POST', body: JSON.stringify(payload) }),
    onSuccess: (result) => {
      setPreview(result)
      setError(null)
    },
    onError: (caught) => setError(message(caught)),
  })
  const save = useMutation({
    mutationFn: () => apiData<Template>('/api/v1/document-templates', { method: 'POST', body: JSON.stringify(payload) }),
    onSuccess: async (result) => {
      await queryClient.invalidateQueries({ queryKey: ['document-templates', organizationId, entity.id] })
      setError(null)
      notify(`Template saved as version ${result.current_version?.version ?? ''}.`, 'success')
    },
    onError: (caught) => setError(message(caught)),
  })

  return (
    <div className="grid gap-4 lg:grid-cols-2">
      <form className="flex flex-col gap-4" onSubmit={(event: FormEvent) => { event.preventDefault(); previewMutation.mutate() }}>
        <Field label="Document type" htmlFor="document-type">
          <Select
            id="document-type"
            value={documentType}
            options={(types.data?.types ?? [{ key: 'offer_letter', label: 'Offer letter', official: false }]).map((type) => ({
              value: type.key,
              label: type.official ? `${type.label} · official` : type.label,
            }))}
            onChange={(event) => setDocumentType(event.target.value)}
          />
        </Field>
        <TextField label="Template name" value={name} onChange={setName} />
        <Field label="Body" htmlFor="template-body">
          <Textarea id="template-body" className="min-h-48" value={body} onChange={(event) => setBody(event.target.value)} />
        </Field>
        <TextField label="Footer note" value={footerNote} onChange={setFooterNote} />
        {selectedType?.official ? (
          <p className="text-sm text-muted-foreground">This official certificate keeps the prescribed layout and omits OneFlo attribution.</p>
        ) : null}
        {error ? <p className="text-sm text-destructive">{error}</p> : null}
        <div className="flex w-fit flex-wrap gap-3">
          <Button type="submit" variant="outline" disabled={!canPreview || previewMutation.isPending}>
            {previewMutation.isPending ? 'Preparing preview…' : 'Preview'}
          </Button>
          <Button type="button" disabled={!canGenerate || save.isPending} onClick={() => save.mutate()}>
            {save.isPending ? 'Saving…' : 'Save new version'}
          </Button>
        </div>
        <p className="text-xs text-muted-foreground">Preview uses sample employee details and does not save the template. Saved versions stay unchanged.</p>
      </form>
      <div className="min-h-80 overflow-hidden rounded-lg border border-border bg-white">
        {preview ? (
          <iframe title="Document preview" className="h-[32rem] w-full" sandbox="" srcDoc={preview.html} />
        ) : (
          <p className="p-4 text-sm text-muted-foreground">Preview the template before saving it.</p>
        )}
      </div>
    </div>
  )
}

function FileButton({ label, path, kind }: { label: string; path: string; kind?: string }) {
  const { notify } = useToast()
  const [pending, setPending] = useState(false)
  const [error, setError] = useState<string | null>(null)

  return (
    <label className="inline-flex cursor-pointer flex-col text-sm">
      <span className="inline-flex h-10 items-center rounded-md border border-border px-3">{pending ? 'Uploading…' : label}</span>
      <input
        className="sr-only"
        type="file"
        accept="image/png,image/jpeg,image/webp"
        onChange={(event) => {
          const file = event.target.files?.[0]
          if (!file) {
            return
          }
          const body = new FormData()
          body.append('file', file)
          if (kind) {
            body.append('kind', kind)
          }
          setPending(true)
          setError(null)
          void apiFetch(path, { method: 'POST', body })
            .then(async (response) => {
              if (!response.ok) {
                throw new ApiError('The file could not be uploaded.', response.status)
              }
              notify(`${label} uploaded.`, 'success')
            })
            .catch((caught: unknown) => setError(message(caught)))
            .finally(() => setPending(false))
        }}
      />
      {error ? <span className="text-destructive">{error}</span> : null}
    </label>
  )
}

function TextField({ label, value, onChange }: { label: string; value: string; onChange: (value: string) => void }) {
  const id = label.toLowerCase().replaceAll(' ', '-')
  return (
    <Field label={label} htmlFor={id}>
      <Input id={id} value={value} onChange={(event) => onChange(event.target.value)} />
    </Field>
  )
}

function blankToNull<T extends Record<string, unknown>>(values: T): T {
  return Object.fromEntries(Object.entries(values).map(([key, value]) => [key, value === '' ? null : value])) as T
}

function message(error: unknown): string {
  return error instanceof ApiError ? (Object.values(error.errors)[0]?.[0] ?? error.message) : 'The request could not be completed.'
}
