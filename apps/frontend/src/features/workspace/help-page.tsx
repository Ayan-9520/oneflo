import { PageHeader } from '@/components/ui/page-header'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

const topics = [
  {
    title: 'Navigation follows permissions',
    body: 'The sidebar lists pages the signed-in account can open in the active business. A direct address still refuses a page the account cannot open.',
  },
  {
    title: 'Every record stays in a business',
    body: 'Choose a business in the switcher. Brands, branches, employees, and roles are loaded for that business only.',
  },
  {
    title: 'Sign in with your issued account',
    body: 'OneFlo uses a secure session cookie. Password reset and an optional authentication code are available from the sign-in page.',
  },
]

export function HelpPage() {
  return (
    <div>
      <PageHeader
        title="Help center"
        description="How the OneFlo workspace is organized."
        crumbs={[{ label: 'Workspace', to: '/' }, { label: 'Help' }]}
      />
      <div className="grid gap-4 md:grid-cols-3">
        {topics.map((topic) => (
          <Card key={topic.title} className="shadow-[var(--shadow-card)]">
            <CardHeader>
              <CardTitle>{topic.title}</CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm text-muted-foreground">{topic.body}</p>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  )
}
