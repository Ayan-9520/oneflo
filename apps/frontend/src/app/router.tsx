import { Navigate, createBrowserRouter, useLocation, RouterProvider } from 'react-router'

import { navigation } from '@/app/navigation'
import { useSession } from '@/app/session'
import { AppShell } from '@/components/layout/app-shell'
import { ErrorState } from '@/components/ui/error-state'
import { ForgotPasswordPage, ResetPasswordPage } from '@/features/auth/password-pages'
import { LoginPage } from '@/features/auth/login-page'
import { DesignSystemPage } from '@/features/design-system/design-system-page'
import { AccessPage, BusinessesPage } from '@/features/organization/records-pages'
import { PeoplePage } from '@/features/organization/people-page'
import { EmployeeProfilePage } from '@/features/organization/employee-profile-page'
import { TeamPage } from '@/features/organization/team-page'
import { AttendancePage } from '@/features/attendance/attendance-page'
import { ApprovalsPage } from '@/features/attendance/approvals-page'
import { LeavePage } from '@/features/attendance/leave-page'
import { PayrollPage } from '@/features/payroll/payroll-page'
import { AssetsPage, EngagementPage, ExpensesPage, HelpdeskPage, PerformancePage } from '@/features/operations/operations-pages'
import { OnboardingPage } from '@/features/recruitment/onboarding-page'
import { RecruitmentPage } from '@/features/recruitment/recruitment-page'
import { LetterStudioPage } from '@/features/recruitment/letter-studio-page'
import { DocumentSettingsPage } from '@/features/documents/document-settings-page'
import { HelpPage } from '@/features/workspace/help-page'
import { ModulePage } from '@/features/workspace/module-page'
import { ManagerPage, MyWorkPage } from '@/features/portal/portal-page'
import { ReportsPage } from '@/features/reports/reports-page'
import { PlatformPage } from '@/features/saas/platform-page'
import { OverviewPage } from '@/features/workspace/overview-page'
import { AssistantPage, LoanPage, NotificationsPage, OrgChartPage, ProfilePage, TasksPage } from '@/features/workspace/hub-pages'
import { BrandMark } from '@/components/brand-mark'

const dedicated = new Set(['/', '/help', '/design-system', '/businesses', '/people', '/team', '/access', '/documents', '/attendance', '/leave', '/approvals', '/payroll', '/recruitment', '/letters', '/onboarding', '/performance', '/assets', '/expenses', '/helpdesk', '/engagement', '/my-work', '/manager', '/reports', '/platform'])

const moduleRoutes = navigation
  .filter((item) => !dedicated.has(item.to))
  .map((item) => ({
    path: item.to.slice(1),
    element: <ModulePage />,
  }))

function OpeningWorkspace() {
  return (
    <div className="flex min-h-svh bg-[#f7f6fb] text-[#1c1433]">
      <aside className="hidden w-72 shrink-0 flex-col bg-[#24156b] px-4 py-5 lg:flex">
        <BrandMark tone="dark" />
        <div className="mt-8 flex flex-col gap-2">
          {['w-40', 'w-32', 'w-36', 'w-28', 'w-40', 'w-24'].map((width) => (
            <div key={width} className={`h-9 ${width} animate-pulse rounded-full bg-white/10`} />
          ))}
        </div>
      </aside>
      <div className="flex min-w-0 flex-1 flex-col">
        <div className="h-1.5 bg-gradient-to-r from-[#2a1878] via-[#7a2cff] to-[#ff2d87]" />
        <div className="flex flex-1 flex-col justify-center px-6 py-10 sm:px-10">
          <BrandMark />
          <h1 className="mt-4 text-3xl font-extrabold tracking-tight">Opening OneFlo</h1>
          <p className="mt-2 max-w-md text-sm text-[#6d6586]">Your workspace is being prepared.</p>
          <div className="mt-8 grid max-w-3xl gap-4 sm:grid-cols-2">
            <div className="h-28 animate-pulse rounded-2xl bg-white shadow-[0_16px_40px_rgb(36_21_107_/_0.08)]" />
            <div className="h-28 animate-pulse rounded-2xl bg-white shadow-[0_16px_40px_rgb(36_21_107_/_0.08)]" />
          </div>
        </div>
      </div>
    </div>
  )
}

function RequireAccount() {
  const { status, account } = useSession()
  const location = useLocation()

  if (status === 'loading') {
    return <OpeningWorkspace />
  }

  if (!account) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  return <AppShell />
}

const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/forgot-password', element: <ForgotPasswordPage /> },
  { path: '/reset-password', element: <ResetPasswordPage /> },
  {
    path: '/',
    element: <RequireAccount />,
    children: [
      { index: true, element: <OverviewPage /> },
      { path: 'help', element: <HelpPage /> },
      { path: 'design-system', element: <DesignSystemPage /> },
      { path: 'businesses', element: <BusinessesPage /> },
      { path: 'people', element: <PeoplePage /> },
      { path: 'people/:employeeId', element: <EmployeeProfilePage /> },
      { path: 'team', element: <TeamPage /> },
      { path: 'access', element: <AccessPage /> },
      { path: 'documents', element: <DocumentSettingsPage /> },
      { path: 'attendance', element: <AttendancePage /> },
      { path: 'leave', element: <LeavePage /> },
      { path: 'approvals', element: <ApprovalsPage /> },
      { path: 'payroll', element: <PayrollPage /> },
      { path: 'recruitment', element: <RecruitmentPage /> },
      { path: 'letters', element: <LetterStudioPage /> },
      { path: 'onboarding', element: <OnboardingPage /> },
      { path: 'performance', element: <PerformancePage /> },
      { path: 'assets', element: <AssetsPage /> },
      { path: 'expenses', element: <ExpensesPage /> },
      { path: 'helpdesk', element: <HelpdeskPage /> },
      { path: 'engagement', element: <EngagementPage /> },
      { path: 'my-work', element: <MyWorkPage /> },
      { path: 'manager', element: <ManagerPage /> },
      { path: 'reports', element: <ReportsPage /> },
      { path: 'platform', element: <PlatformPage /> },
      { path: 'org-chart', element: <OrgChartPage /> },
      { path: 'assistant', element: <AssistantPage /> },
      { path: 'notifications', element: <NotificationsPage /> },
      { path: 'profile', element: <ProfilePage /> },
      { path: 'loans', element: <LoanPage /> },
      { path: 'tasks', element: <TasksPage /> },
      ...moduleRoutes,
      {
        path: '*',
        element: <ErrorState title="This page does not exist" description="The address is not part of the OneFlo workspace." />,
      },
    ],
  },
])

export function AppRouter() {
  return <RouterProvider router={router} />
}
