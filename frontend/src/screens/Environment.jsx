import { useEffect, useState } from 'preact/hooks'
import { get, post, addressFor } from '../api.js'
import { Card } from '../components/Card.jsx'
import { Pill } from '../components/Pill.jsx'
import { Button } from '../components/Button.jsx'
import { MoveForm } from '../components/MoveForm.jsx'
import { ExposureNotice } from '../components/ExposureNotice.jsx'

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

// Owner-only. Runs the same form as the installer, but asks the signed-in
// route. The session cookie belongs to the old address, so the owner signs in
// again at the new one.
function MoveCard() {
  async function move(name) {
    const moved = await post('admin/rename', { name })
    location.assign(addressFor(moved.redirect, '?moved=1').href)
  }

  return (
    <Card title="Move this tool">
      <MoveForm onMove={move} label="Move this tool" />
    </Card>
  )
}

export function Environment({ me, selfTest, onSignOut }) {
  const [env, setEnv] = useState(null)
  const [error, setError] = useState(null)

  useEffect(() => {
    get('env').then(setEnv).catch((e) => setError(e.message))
  }, [])

  const ready = env?.environment?.php_satisfied === true

  return (
    <main class="min-h-screen bg-surface-sunken px-6 py-12 font-sans text-ink antialiased">
      <div class="mx-auto max-w-xl space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h1 class="font-display text-3xl font-semibold tracking-tight">WP SafeMode</h1>
            <p class="mt-1 text-sm text-ink-muted">
              Signed in as {me.user.login}. Nothing is being watched yet — this page only reports what this server
              can do.
            </p>
          </div>
          <Button tone="quiet" onClick={onSignOut}>
            Sign out
          </Button>
        </header>

        {selfTest?.reachable && <ExposureNotice detail={selfTest.detail} />}

        {error && (
          <Card>
            <Pill tone="bad">Can't reach the server</Pill>
            <p class="mt-3 text-sm text-ink-muted">
              The page loaded but <code class="font-mono text-xs text-ink">api/env</code> did not answer ({error}). The
              tool's own PHP is not responding, so nothing here reflects the state of your site.
            </p>
          </Card>
        )}

        {env && (
          <Card
            title={ready ? 'This server can run WP SafeMode' : 'This server cannot run WP SafeMode'}
            aside={<Pill tone={ready ? 'good' : 'bad'}>{ready ? 'Ready' : 'Blocked'}</Pill>}
          >
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
          </Card>
        )}

        {!env && !error && <p class="text-sm text-ink-faint">Checking this server…</p>}

        {me.user.role === 'owner' && <MoveCard />}
      </div>
    </main>
  )
}
