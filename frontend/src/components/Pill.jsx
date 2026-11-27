export function Pill({ tone, children }) {
  const tones = {
    good: 'bg-green/10 text-green ring-green/20',
    bad: 'bg-danger/10 text-danger-deep ring-danger/20',
    idle: 'bg-surface-raised text-ink-muted ring-edge',
  }

  return (
    <span class={`inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ring-1 ring-inset ${tones[tone]}`}>
      {children}
    </span>
  )
}
