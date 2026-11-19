import type { ReactNode } from 'react'

export type Column<T> = {
  id: string
  header: string
  cell: (row: T) => ReactNode
  className?: string
}

export function DataTable<T>({
  columns,
  rows,
  getRowId,
  caption,
}: {
  columns: readonly Column<T>[]
  rows: readonly T[]
  getRowId: (row: T) => string
  caption: string
}) {
  return (
    <div className="overflow-x-auto rounded-2xl border border-border bg-card shadow-[var(--shadow-card)]">
      <table className="w-full min-w-[36rem] border-collapse text-left text-sm">
        <caption className="sr-only">{caption}</caption>
        <thead className="border-b border-border text-[13px] text-muted-foreground">
          <tr>
            {columns.map((column) => (
              <th key={column.id} scope="col" className="px-4 py-3.5 font-medium">
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={getRowId(row)} className="border-t border-border transition-colors hover:bg-[#f7f8fd]">
              {columns.map((column) => (
                <td key={column.id} className={`px-4 py-3 ${column.className ?? ''}`}>
                  {column.cell(row)}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
