export function Button({ tone = 'primary', busy, disabled, children, ...rest }) {
  const tones = {
    primary: 'bg-green text-surface hover:bg-green-hover active:bg-green-active',
    quiet: 'border border-edge-strong bg-surface text-ink hover:bg-surface-sunken',
  }

  return (
    <button
      type="button"
      disabled={busy || disabled}
      class={`inline-flex items-center justify-center rounded-panel px-4 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-green/40 disabled:cursor-not-allowed disabled:opacity-50 ${tones[tone]}`}
      {...rest}
    >
      {children}
    </button>
  )
}
