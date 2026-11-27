import { useState } from 'preact/hooks'
import { Field } from './Field.jsx'
import { Button } from './Button.jsx'
import { Notice } from './Notice.jsx'

const SAFE_NAME = /^[A-Za-z0-9_-]{3,64}$/

// wpsm- plus 12 lowercase hex characters, drawn in the browser so the name
// is not guessable from anything the server sends before the move.
function suggestName() {
  const bytes = crypto.getRandomValues(new Uint8Array(6))

  return 'wpsm-' + Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('')
}

// Shared by the installer's move step and the owner's "Move this tool" card.
// `onMove(name)` does the work and throws on failure; the parent decides what
// happens next, because the installer and the signed-in panel differ there.
export function MoveForm({ onMove, label, children }) {
  const [name, setName] = useState(suggestName)
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState(null)

  const valid = SAFE_NAME.test(name)

  async function submit() {
    setBusy(true)
    setError(null)

    try {
      await onMove(name)
    } catch (e) {
      setError(e.message)
      setBusy(false)
    }
  }

  return (
    <div class="space-y-4">
      <p class="text-sm leading-relaxed text-ink">
        Right now this tool sits at a name anyone can guess. Move it somewhere they can't.
      </p>

      <Field
        label="New folder name"
        value={name}
        mono
        onInput={setName}
        error={name !== '' && !valid ? 'Use 3 to 64 letters, digits, dashes or underscores.' : null}
        hint="After the move this tool lives at the new address. Anything bookmarked at the old one stops working."
      />

      {error && (
        <Notice tone="danger" title="The move did not happen">
          {error} You can try again or pick another name.
        </Notice>
      )}

      <div class="flex flex-wrap gap-3">
        <Button onClick={submit} busy={busy} disabled={!valid}>
          {busy ? 'Moving…' : label}
        </Button>
        {children}
      </div>
    </div>
  )
}
