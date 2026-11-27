export function Field({ label, hint, error, id, type = 'text', value, onInput, disabled, mono, autoComplete }) {
  const fieldId = id || `field-${label.toLowerCase().replace(/[^a-z0-9]+/g, '-')}`

  return (
    <div>
      <label for={fieldId} class="block text-sm font-medium text-ink">
        {label}
      </label>
      <input
        id={fieldId}
        type={type}
        value={value}
        disabled={disabled}
        autoComplete={autoComplete ?? (type === 'password' ? 'current-password' : 'off')}
        spellcheck={false}
        autoCapitalize="none"
        aria-invalid={error ? 'true' : undefined}
        aria-describedby={hint || error ? `${fieldId}-note` : undefined}
        onInput={(e) => onInput(e.currentTarget.value)}
        class={`mt-1.5 block w-full rounded-panel border bg-surface px-3 py-2 text-sm text-ink shadow-sm focus:border-green focus:outline-none focus:ring-2 focus:ring-green/30 disabled:opacity-50 ${
          error ? 'border-danger' : 'border-edge-strong'
        } ${mono ? 'font-mono' : ''}`}
      />
      {(hint || error) && (
        <p id={`${fieldId}-note`} class={`mt-1.5 text-xs ${error ? 'text-danger-deep' : 'text-ink-faint'}`}>
          {error || hint}
        </p>
      )}
    </div>
  )
}
