import { useState, type ReactNode } from 'react'

import { cn } from '@/lib/utils'

export type TabItem = {
  id: string
  label: string
  content: ReactNode
}

export function Tabs({ tabs, defaultTab }: { tabs: readonly TabItem[]; defaultTab?: string }) {
  const [active, setActive] = useState(defaultTab ?? tabs[0]?.id ?? '')
  const current = tabs.find((tab) => tab.id === active) ?? tabs[0]

  return (
    <div>
      <div role="tablist" aria-label="Sections" className="flex gap-1 overflow-x-auto border-b border-border">
        {tabs.map((tab) => {
          const selected = tab.id === current?.id
          return (
            <button
              key={tab.id}
              type="button"
              role="tab"
              aria-selected={selected}
              className={cn(
                'shrink-0 border-b-2 px-3 py-2 text-sm font-medium',
                selected ? 'border-primary text-primary-dark' : 'border-transparent text-muted-foreground hover:text-foreground',
              )}
              onClick={() => setActive(tab.id)}
            >
              {tab.label}
            </button>
          )
        })}
      </div>
      <div role="tabpanel" className="pt-5">
        {current?.content}
      </div>
    </div>
  )
}
