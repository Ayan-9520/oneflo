import { Navigate, createBrowserRouter, useLocation, RouterProvider } from 'react-router'

import { navigation } from '@/app/navigation'
import { hasSessionHint, useSession } from '@/app/session'
import { AppShell } from '@/components/layout/app-shell'
import { ErrorState } from '@/components/ui/error-state'
import { ForgotPasswordPage, ResetPasswordPage } from '@/features/auth/password-pages'
import { LoginPage } from '@/features/auth/login-page'
import { DesignSystemPage } from '@/features/design-system/design-system-page'
import { AccessPage, BusinessesPage } from '@/features/organization/records-pages'
import { MasterDataPage } from '@/features/organization/master-data-page'
import { PeoplePage } from '@/features/organization/people-page'
import { EmployeeProfilePage } from '@/features/organization/employee-profile-page'
import { TeamPage } from '@/features/organization/team-page'
import { AttendancePage } from '@/features/attendance/attendance-page'
import { ApprovalsPage } from '@/features/attendance/approvals-page'
import { LeavePage } from '@/features/attendance/leave-page'
import { PayrollPage } from '@/features/payroll/payroll-page'
import { AssetsPage, EngagementPage, ExpensesPage, HelpdeskPage, PerformancePage } from '@/features/operations/operations-pages'
import { OnboardingPage } from '@/features/recruitment/onboarding-page'
import { CandidateOAuthCallback, CandidateProfilePage } from '@/features/recruitment/candidate-account'
import { CandidateLoginPage } from '@/features/recruitment/candidate-auth-page'
import { CareerJobPage, CareersPage } from '@/features/recruitment/careers-page'
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
import { HomePage } from '@/features/home/home-page'
import { CategoriesPage, CompaniesPage, HowItWorksPage } from '@/features/home/public-pages'

const dedicated = new Set(['/', '/help', '/design-system', '/businesses', '/master-data', '/people', '/team', '/access', '/documents', '/attendance', '/leave', '/approvals', '/payroll', '/recruitment', '/letters', '/onboarding', '/performance', '/assets', '/expenses', '/helpdesk', '/engagement', '/my-work', '/manager', '/reports', '/platform'])

const moduleRoutes = navigation
  .filter((item) => !dedicated.has(item.to))
  .map((item) => ({
    path: item.to.slice(1),
    element: <ModulePage />,
  }))

function OpeningWorkspace() {
  return (
    <div role="status" aria-live="polite" className="relative flex min-h-svh items-center justify-center overflow-hidden bg-gradient-to-br from-[#ffe4f2] via-[#f3ecff] to-[#e3edff] px-6 text-[#1c1433]">
      <div className="pointer-events-none absolute -left-32 -top-32 size-[30rem] animate-pulse rounded-full bg-[#ff9ed2]/40 blur-3xl" />
      <div className="pointer-events-none absolute -bottom-40 -right-32 size-[34rem] animate-pulse rounded-full bg-[#9cc4ff]/50 blur-3xl [animation-delay:1s]" />
      <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(rgb(58_53_224_/_0.08)_1px,transparent_1px)] [background-size:22px_22px]" />
      <div className="relative flex flex-col items-center text-center">
        <div className="relative flex size-36 items-center justify-center">
          <span className="absolute inset-0 animate-spin rounded-full bg-[conic-gradient(from_0deg,#ff3d9a,#a855f7,#3a35e0,transparent_70%)] [animation-duration:1.6s]" />
          <span className="absolute inset-[5px] rounded-full bg-[#f7f3ff]" />
          <span className="absolute inset-4 animate-ping rounded-full bg-[#c4b5fd]/30 [animation-duration:2s]" />
          <span className="relative flex size-20 items-center justify-center rounded-3xl bg-gradient-to-br from-[#3a35e0] via-[#6d38d6] to-[#e23d9a] text-2xl font-extrabold text-white shadow-[0_18px_40px_rgb(109_56_214_/_0.4)]">1F</span>
        </div>
        <div className="mt-8"><BrandMark /></div>
        <h1 className="mt-3 text-2xl font-extrabold tracking-tight sm:text-3xl">Welcome back</h1>
        <p className="mt-2 text-sm text-[#6d6586]">Opening your workspace…</p>
        <div className="mt-6 h-1.5 w-56 overflow-hidden rounded-full bg-white/70">
          <div className="h-full w-1/3 animate-[oneflo-load_1.4s_ease-in-out_infinite] rounded-full bg-gradient-to-r from-[#ff3d9a] via-[#a855f7] to-[#3a35e0]" />
        </div>
        <div className="mt-8 flex flex-wrap justify-center gap-2 text-xs font-semibold text-[#5b4b8a]">
          {['People', 'Attendance', 'Payroll', 'Recruitment'].map((label, index) => (
            <span key={label} style={{ animationDelay: `${index * 0.2}s` }} className="animate-pulse rounded-full border border-white bg-white/70 px-3 py-1 shadow-sm backdrop-blur">{label}</span>
          ))}
        </div>
      </div>
      <style>{'@keyframes oneflo-load{0%{transform:translateX(-110%)}100%{transform:translateX(320%)}}'}</style>
    </div>
  )
}

function RequireAccount() {
  const { status, account } = useSession()
  const location = useLocation()

  if (status === 'loading') {
    if (location.pathname === '/' && !hasSessionHint()) return <HomePage />
    return <OpeningWorkspace />
  }

  if (!account) {
    if (location.pathname === '/') return <HomePage />
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  return <AppShell />
}

const router = createBrowserRouter([
  { path: '/login', element: <LoginPage /> },
  { path: '/home', element: <HomePage /> },
  { path: '/categories', element: <CategoriesPage /> },
  { path: '/companies', element: <CompaniesPage /> },
  { path: '/how-it-works', element: <HowItWorksPage /> },
  { path: '/career', element: <Navigate to="/careers" replace /> },
  { path: '/careers', element: <CareersPage /> },
  { path: '/careers/jobs/:slug', element: <CareerJobPage /> },
  { path: '/careers/login', element: <CandidateLoginPage /> },
  { path: '/careers/auth/:provider', element: <CandidateOAuthCallback /> },
  { path: '/careers/profile', element: <CandidateProfilePage /> },
  { path: '/careers/:company', element: <CareersPage /> },
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
      { path: 'master-data', element: <MasterDataPage /> },
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
