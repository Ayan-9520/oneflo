export type NamedRef = { id: number; name: string }

export type EmployeeRecord = {
  id: number
  employee_code: string
  given_name: string
  family_name: string
  full_name: string
  email: string | null
  phone: string | null
  has_photo: boolean
  employment_type: string
  joined_on: string | null
  work_location: string | null
  status: string
  brand: NamedRef | null
  legal_entity: NamedRef | null
  department: NamedRef | null
  designation: NamedRef | null
  branch: NamedRef | null
  manager: { id: number; full_name: string } | null
}

export type EmploymentEvent = {
  id: number
  organization_id: number
  change_type: string
  status: string
  effective_on: string | null
  ended_on: string | null
  last_working_on: string | null
  reason: string | null
  employment_type: string
}

export const employmentTypes = [
  { value: 'permanent', label: 'Permanent' },
  { value: 'contract', label: 'Contract' },
  { value: 'intern', label: 'Intern' },
  { value: 'consultant', label: 'Consultant' },
]

export const employmentStatuses = [
  { value: '', label: 'All statuses' },
  { value: 'active', label: 'Active' },
  { value: 'inactive', label: 'Inactive' },
  { value: 'resigned', label: 'Resigned' },
  { value: 'exited', label: 'Exited' },
  { value: 'transferred', label: 'Transferred' },
]
