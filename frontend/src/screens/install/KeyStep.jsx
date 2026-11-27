import { useEffect, useState } from 'preact/hooks'
import { post } from '../../api.js'
import { Card } from '../../components/Card.jsx'
import { Field } from '../../components/Field.jsx'
import { Button } from '../../components/Button.jsx'
import { Notice } from '../../components/Notice.jsx'
import { ExposureNotice } from '../../components/ExposureNotice.jsx'

export function KeyStep({ token, onStored }) {
  const [test, setTest] = useState(null)
  const [testError, setTestError] = useState(null)
  const [acknowledged, setAcknowledged] = useState(false)
  const [key, setKey] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  useEffect(() => {
    post('install/self-test', { token })
      .then((body) => setTest(body))
      .catch((e) => setTestError(e.message))
  }, [token])

  // Mirrors the server: "could not check" is treated like "reachable".
  const blocked = test !== null && (test.reachable || test.inconclusive)
  const needsAck = blocked || testError !== null
  const locked = (test === null && testError === null) || (needsAck && !acknowledged)

  async function submit(event) {
    event.preventDefault()
    setBusy(true)
    setError(null)

    try {
      await post('install/api-key', { token, api_key: key, acknowledged_risk: acknowledged })
      onStored()
    } catch (e) {
      setError(e.message)
      setBusy(false)
    }
  }

  return (
    <Card title="Check this folder, then add your key">
      <div class="space-y-4">
        {test === null && !testError && <p class="text-sm text-ink-faint">Checking whether this folder can be read from the web…</p>}

        {testError && (
          <Notice tone="warn" title="The check could not run">
            {testError} You can still go on, but only after you confirm below.
          </Notice>
        )}

        {test && test.reachable && <ExposureNotice detail={test.detail} />}

        {test && !test.reachable && test.inconclusive && (
          <Notice tone="warn" title="We could not tell whether the data folder is exposed">
            {test.detail}
          </Notice>
        )}

        {test && !blocked && (
          <Notice tone="good" title="The data folder is not readable from the web">
            {test.detail}
          </Notice>
        )}

        {needsAck && (
          <label class="flex items-start gap-3 text-sm text-ink">
            <input
              type="checkbox"
              checked={acknowledged}
              onChange={(e) => setAcknowledged(e.currentTarget.checked)}
              class="mt-0.5"
            />
            <span>I understand the risk and want to continue anyway.</span>
          </label>
        )}

        <form class="space-y-4" onSubmit={submit}>
          <p class="text-sm leading-relaxed text-ink-muted">
            Your key is encrypted on disk, but the key that unlocks it lives in the same folder. That protects you
            from a leaked backup or a stray directory listing — not from someone who can read every file on the
            server.
          </p>

          <Field
            label="Claude API key"
            type="password"
            value={key}
            onInput={setKey}
            autoComplete="off"
            mono
            disabled={locked}
            error={error}
          />

          <div class="flex flex-wrap gap-3">
            <Button type="submit" busy={busy} disabled={key.trim() === '' || locked}>
              {busy ? 'Saving…' : 'Save key'}
            </Button>
          </div>
        </form>
      </div>
    </Card>
  )
}
