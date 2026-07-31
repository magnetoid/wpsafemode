import { useEffect, useState } from 'preact/hooks'

// Relative, no leading slash — same reason as `base: './'` in vite.config.js.
const ENDPOINT = 'api/env'

function Pill({ tone, children }) {
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

function Row({ label, value, note }) {
  return (
    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1 border-t border-edge py-3 first:border-t-0">
      <dt class="text-sm text-ink-muted">{label}</dt>
      <dd class="text-right">
        <span class="text-sm font-medium text-ink">{value}</span>
        {note && <span class="mt-0.5 block text-xs text-ink-faint">{note}</span>}
      </dd>
    </div>
  )
}

export function App() {
  const [env, setEnv] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    fetch(ENDPOINT)
      .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`))))
      .then(setEnv)
      .catch((e) => setError(e.message))
  }, [])

  const ready = env?.environment?.php_satisfied === true

  return (
    <main class="min-h-screen bg-surface-sunken px-6 py-12 font-sans text-ink antialiased">
      <div class="mx-auto max-w-xl">
        <header class="mb-8">
          <h1 class="font-display text-3xl font-semibold tracking-tight">WP SafeMode</h1>
          <p class="mt-1 text-sm text-ink-muted">
            Skeleton build. Nothing is being watched yet — this page only reports what
            this server can do.
          </p>
        </header>

        {error && (
          <section class="rounded-card border border-danger/30 bg-surface p-6 shadow-card">
            <Pill tone="bad">Can't reach the server</Pill>
            <p class="mt-3 text-sm text-ink-muted">
              The page loaded but <code class="font-mono text-xs text-ink">{ENDPOINT}</code>{' '}
              did not answer ({error}). The tool's own PHP is not responding, so nothing
              here reflects the state of your site.
            </p>
          </section>
        )}

        {env && (
          <section class="rounded-card border border-edge bg-surface p-6 shadow-card">
            <div class="mb-5 flex items-center justify-between gap-4">
              <h2 class="font-display text-lg font-semibold">
                {ready ? 'This server can run WP SafeMode' : 'This server cannot run WP SafeMode'}
              </h2>
              <Pill tone={ready ? 'good' : 'bad'}>{ready ? 'Ready' : 'Blocked'}</Pill>
            </div>

            <dl>
              <Row label="Version" value={env.app.version} />

              <Row
                label="PHP"
                value={env.environment.php_version}
                note={ready ? 'meets the 8.0 minimum' : 'below the 8.0 minimum — nothing will run'}
              />

              <Row
                label="Storage"
                value={env.storage.backend}
                note={
                  env.storage.backend === 'sqlite'
                    ? 'one database file, chosen at install'
                    : 'JSON files — pdo_sqlite was unavailable at install'
                }
              />

              <Row
                label="WordPress"
                value={env.environment.wp_detected ? 'found' : 'not found'}
                note={
                  env.environment.wp_detected
                    ? 'sitting next to this folder'
                    : 'not in any of the usual places around this folder'
                }
              />
            </dl>
          </section>
        )}

        {!env && !error && <p class="text-sm text-ink-faint">Checking this server…</p>}
      </div>
    </main>
  )
}
