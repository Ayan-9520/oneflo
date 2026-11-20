import {
  BarChart3,
  Briefcase,
  Building2,
  CalendarCheck,
  CalendarDays,
  ClipboardCheck,
  Component,
  FileText,
  LayoutDashboard,
  Laptop,
  LifeBuoy,
  Megaphone,
  Receipt,
  Shield,
  Users,
  UserPlus,
  Wallet,
  type LucideIcon,
} from 'lucide-react'

import { canAccess } from '@/app/session'

export type NavItem = {
  id: string
  label: string
  to: string
  description: string
  icon: LucideIcon
  access: 'everyone' | readonly string[]
}

export const navigation: readonly NavItem[] = [
  {
    id: 'overview',
    label: 'Overview',
    to: '/',
    description: 'Live platform readiness and the workspace entry point.',
    icon: LayoutDashboard,
    access: 'everyone',
  },
  {
    id: 'businesses',
    label: 'Businesses',
    to: '/businesses',
    description: 'Brands, legal entities, and branches for the active business.',
    icon: Building2,
    access: ['organizations.view', 'organization.manage'],
  },
  {
    id: 'master-data',
    label: 'Master data',
    to: '/master-data',
    description: 'Activate department and designation templates for this business, or add your own.',
    icon: Building2,
    access: ['organization.manage'],
  },
  {
    id: 'access',
    label: 'Access',
    to: '/access',
    description: 'Roles and permissions for the active business.',
    icon: Shield,
    access: ['roles.view', 'access.manage'],
  },
  {
    id: 'people',
    label: 'People',
    to: '/people',
    description: 'Employees in the active business.',
    icon: Users,
    access: ['employees.view'],
  },
  {
    id: 'team',
    label: 'Team',
    to: '/team',
    description: 'Employees in the teams you are allowed to see.',
    icon: Users,
    access: ['employees.view_team'],
  },
  {
    id: 'policies',
    label: 'Policies',
    to: '/policies',
    description: 'Attendance, leave, and payroll policies will connect here.',
    icon: FileText,
    access: ['organization.manage'],
  },
  {
    id: 'attendance',
    label: 'Attendance',
    to: '/attendance',
    description: 'Shifts, punches, corrections, and monthly attendance for the active business.',
    icon: CalendarCheck,
    access: ['attendance.view'],
  },
  {
    id: 'leave',
    label: 'Leave',
    to: '/leave',
    description: 'Leave types, balances, accrual, and applications for the active business.',
    icon: CalendarDays,
    access: ['leave.view'],
  },
  {
    id: 'approvals',
    label: 'Approvals',
    to: '/approvals',
    description: 'Manager and HR queues for attendance and leave.',
    icon: ClipboardCheck,
    access: ['leave.approve', 'attendance.approve'],
  },
  {
    id: 'payroll',
    label: 'Payroll',
    to: '/payroll',
    description: 'Salary structures, confirmed statutory rules, and locked payroll for the legal employer.',
    icon: Wallet,
    access: ['payroll.view'],
  },
  {
    id: 'recruitment',
    label: 'Recruitment',
    to: '/recruitment',
    description: 'Requisitions, candidates, interviews, and offers for the active business.',
    icon: Briefcase,
    access: ['recruitment.view'],
  },
  {
    id: 'letters',
    label: 'Letters',
    to: '/letters',
    description: 'Offer, appointment, and joining letters, with a live preview.',
    icon: FileText,
    access: ['documents.generate', 'recruitment.manage'],
  },
  {
    id: 'onboarding',
    label: 'Onboarding',
    to: '/onboarding',
    description: 'Joining checklist, identity tracking, and probation for the active business.',
    icon: UserPlus,
    access: ['onboarding.manage', 'documents.view'],
  },
  {
    id: 'performance',
    label: 'Performance',
    to: '/performance',
    description: 'KPIs, goals, reviews, and promotion recommendations.',
    icon: BarChart3,
    access: ['performance.view'],
  },
  {
    id: 'assets',
    label: 'Assets',
    to: '/assets',
    description: 'Company inventory, allocation, return, and maintenance.',
    icon: Laptop,
    access: ['assets.view'],
  },
  {
    id: 'expenses',
    label: 'Expenses',
    to: '/expenses',
    description: 'Expense claims, receipts, and manager then finance approval.',
    icon: Receipt,
    access: ['expenses.view', 'expenses.submit'],
  },
  {
    id: 'helpdesk',
    label: 'Helpdesk',
    to: '/helpdesk',
    description: 'Employee queries, assignment, and SLA tracking.',
    icon: LifeBuoy,
    access: ['helpdesk.view'],
  },
  {
    id: 'engagement',
    label: 'Engagement',
    to: '/engagement',
    description: 'Announcements, events, reminders, feedback, and recognition.',
    icon: Megaphone,
    access: ['engagement.view'],
  },
  {
    id: 'documents',
    label: 'Documents',
    to: '/documents',
    description: 'Company profile, branding, and document templates for the active business.',
    icon: FileText,
    access: ['documents.view', 'documents.generate', 'branding.manage'],
  },
  {
    id: 'platform',
    label: 'Platform',
    to: '/platform',
    description: 'Organization approval, plans, billing, and tenant-scoped assistant tools.',
    icon: Building2,
    access: ['billing.view', 'billing.manage'],
  },
  {
    id: 'reports',
    label: 'Reports',
    to: '/reports',
    description: 'Headcount, payroll, attendance, and employee reports for the active business.',
    icon: BarChart3,
    access: ['reports.view'],
  },
  {
    id: 'my-work',
    label: 'My work',
    to: '/my-work',
    description: 'Profile, attendance, leave, payslips, documents, and requests for the signed-in employee.',
    icon: Briefcase,
    access: ['employees.view_own'],
  },
  {
    id: 'manager',
    label: 'My team',
    to: '/manager',
    description: 'Attendance, leave approvals, performance, and expenses for the assigned team.',
    icon: Users,
    access: ['employees.view_team', 'leave.approve'],
  },
  {
    id: 'help',
    label: 'Help',
    to: '/help',
    description: 'How this workspace is organized.',
    icon: LifeBuoy,
    access: 'everyone',
  },
  {
    id: 'design-system',
    label: 'Design system',
    to: '/design-system',
    description: 'OneFlo interface components.',
    icon: Component,
    access: 'everyone',
  },
]

export function visibleNavigation(permissions: ReadonlySet<string>) {
  return navigation.filter((item) => canAccess(item.access, permissions))
}

export function findNavigation(pathname: string) {
  return navigation.find((item) => item.to === pathname) ?? null
}
