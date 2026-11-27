import { useState } from 'preact/hooks'
import { post } from '../../api.js'
import { Card } from '../../components/Card.jsx'
import { Field } from '../../components/Field.jsx'
import { Button } from '../../components/Button.jsx'
import { Notice } from '../../components/Notice.jsx'

export function TokenStep({ hasWordPress, onVerified }) {
  const [token, setToken] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  async function submit(event) {
    event.preventDefault()
    setBusy(true)
    setError(null)

    try {
      await post('install/token', { token })
      onVerified(token.trim())
    } catch (e) {
      setError(
        e.code === 'bad_token'
          ? "That token doesn't match. Copy it again from data/install-token.php; it is the code inside the quotes."
          : e.message
      )
      setBusy(false)
    }
  }

  return (
    <Card title="Set up WP SafeMode">
      <form class="space-y-4" onSubmit={submit}>
        {hasWordPress === false && (
          <Notice tone="info">
            No WordPress was found next to this tool. You can still set it up; it will look again when you start.
          </Notice>
        )}

        <p class="text-sm leading-relaxed text-ink-muted">
          Open <code class="font-mono text-xs text-ink">data/install-token.php</code> over FTP or your host's file
          manager and paste the token inside. This proves you're the site owner and not someone who found this page
          first.
        </p>

        <Field label="Install token" value={token} onInput={setToken} mono error={error} />

        <Button type="submit" busy={busy} disabled={token.trim() === ''}>
          {busy ? 'Checking…' : 'Continue'}
        </Button>
      </form>
    </Card>
  )
}
