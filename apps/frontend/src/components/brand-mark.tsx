export function BrandMark({ tone = 'light' }: { tone?: 'light' | 'dark' }) {
  return (
    <span className="text-lg leading-none font-extrabold tracking-tight">
      <span className={tone === 'dark' ? 'text-white' : 'text-[#2f6bff]'}>One</span>
      <span className="text-[#ff2d87]">Flo</span>
    </span>
  )
}
