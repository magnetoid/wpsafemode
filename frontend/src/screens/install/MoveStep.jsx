import { useState } from 'preact/hooks'
import { post, setBase, addressFor } from '../../api.js'
import { Card } from '../../components/Card.jsx'
import { Button } from '../../components/Button.jsx'
import { Notice } from '../../components/Notice.jsx'
import { MoveForm } from '../../components/MoveForm.jsx'

export function MoveStep({ token, onFinished }) {
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)

  // Order matters: the folder moves first, so "finish" has to be asked of the
  // new address. If finish fails after a good move, the new address is still
  // where the tool now lives; the wizard there asks for the token again.
  async function moveAndFinish(name) {
    const moved = await post('install/rename', { token, name })
    const target = addressFor(moved.redirect)

    setBase(target.root)

    try {
      await post('install/finish', { token })
    } catch (e) {
      // Fall through: go to the new address anyway.
    }

    location.assign(target.href)
  }

  async function finishHere() {
    setBusy(true)
    setError(null)

    try {
      await post('install/finish', { token })
      onFinished()
    } catch (e) {
      setError(e.message)
      setBusy(false)
    }
  }

  return (
    <Card title="Move this tool">
      <div class="space-y-4">
        <MoveForm
          onMove={moveAndFinish}
          label="Move and finish"
          lead="Right now this tool sits at a name anyone can guess. Move it somewhere they can't."
        >
          <Button tone="quiet" busy={busy} onClick={finishHere}>
            Finish without moving
          </Button>
        </MoveForm>

        {error && (
          <Notice tone="danger" title="Setup did not finish">
            {error}
          </Notice>
        )}
      </div>
    </Card>
  )
}
