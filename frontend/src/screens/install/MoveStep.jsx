import { useState } from 'preact/hooks'
import { post, setBase, addressFor, installFolder } from '../../api.js'
import { Card } from '../../components/Card.jsx'
import { Button } from '../../components/Button.jsx'
import { Notice } from '../../components/Notice.jsx'
import { MoveForm } from '../../components/MoveForm.jsx'

// The ZIP's own folder name is the one a scanner tries first. Any other name
// (a re-entered wizard after a move, or a folder renamed over FTP) is not
// guessable from the download, so it gets a neutral lead.
function leadFor(folder) {
  return folder === 'wpsafemode'
    ? "Right now this tool sits at a name anyone can guess. Move it somewhere they can't."
    : 'You can move this tool to a new random address before you finish, or keep its current one.'
}

export function MoveStep({ token, onFinished }) {
  const [error, setError] = useState(null)
  const [busy, setBusy] = useState(false)
  const [moving, setMoving] = useState(false)

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
    if (busy || moving) {
      return
    }

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
          lead={leadFor(installFolder())}
          locked={busy}
          onBusy={setMoving}
        >
          <Button tone="quiet" busy={busy} disabled={moving} onClick={finishHere}>
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
