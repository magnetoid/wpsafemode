export function Notice({ tone = 'info', title, children }) {
  const tones = {
    info: 'border-edge bg-surface-raised text-ink',
    warn: 'border-warn/40 bg-warn/10 text-warn-deep',
    danger: 'border-danger/40 bg-danger/10 text-danger-deep',
    good: 'border-green/30 bg-green/10 text-green',
  }

  return (
    <div class={`rounded-panel border p-4 ${tones[tone]}`} role={tone === 'danger' ? 'alert' : undefined}>
      {title && <p class="font-medium">{title}</p>}
      <div class="mt-1 text-sm leading-relaxed">{children}</div>
    </div>
  )
}
