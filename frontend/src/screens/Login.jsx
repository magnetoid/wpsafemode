import { useEffect, useState } from 'preact/hooks'
import { post, setCsrf } from '../api.js'
import { Card } from '../components/Card.jsx'
import { Field } from '../components/Field.jsx'
import { Button } from '../components/Button.jsx'
import { Notice } from '../components/Notice.jsx'

function minutes(seconds) {
  const m = Math.max(1, Math.ceil(seconds / 60))

  return m === 1 ? 'a minute' : `${m} minutes`
}

export function Login({ onSignedIn }) {
  const [login, setLogin] = useState('')
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)
  const [until, setUntil] = useState(0)
  const [now, setNow] = useState(Date.now())

  const moved = new URLSearchParams(location.search).get('moved') === '1'
  const waitSeconds = Math.max(0, Math.ceil((until - now) / 1000))
  const lockedOut = waitSeconds > 0

  useEffect(() => {
    if (until === 0) return undefined

    const timer = setInterval(() => {
      setNow(Date.now())
      if (Date.now() >= until) clearInterval(timer)
    }, 1000)

    return () => clearInterval(timer)
  }, [until])

  async function submit(event) {
    event.preventDefault()
    setBusy(true)
    setError(null)

    try {
      const body = await post('auth/login', { login, password })
      setCsrf(body.csrf)

      onSignedIn(body)
    } catch (e) {
      if (e.code === 'locked_out') {
        setUntil(Date.now() + e.retryAfter * 1000)
        setNow(Date.now())
      } else if (e.code === 'bad_credentials') {
        setError("That name and password don't match. Check both and try again.")
      } else {
        setError(e.message)
      }
      setBusy(false)
    }
  }

  return (
    <main class="min-h-screen bg-surface-sunken px-6 py-12 font-sans text-ink antialiased">
      <div class="mx-auto max-w-md">
        <header class="mb-8">
          <h1 class="font-display text-3xl font-semibold tracking-tight">WP SafeMode</h1>
        </header>

        <Card title="Sign in">
          <form class="space-y-4" onSubmit={submit}>
            {moved && <Notice tone="good">The tool has moved. Sign in again at its new address.</Notice>}

            {lockedOut && (
              <Notice tone="warn" title="Too many attempts">
                Try again in {minutes(waitSeconds)}.
              </Notice>
            )}

            {error && <Notice tone="danger">{error}</Notice>}

            <Field label="Name" value={login} onInput={setLogin} autoComplete="username" />
            <Field label="Password" type="password" value={password} onInput={setPassword} />

            <Button type="submit" busy={busy} disabled={lockedOut || login.trim() === '' || password === ''}>
              {busy ? 'Signing in…' : 'Sign in'}
            </Button>
          </form>
        </Card>
      </div>
    </main>
  )
}
