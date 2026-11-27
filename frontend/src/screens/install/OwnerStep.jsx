import { useState } from 'preact/hooks'
import { post } from '../../api.js'
import { Card } from '../../components/Card.jsx'
import { Field } from '../../components/Field.jsx'
import { Button } from '../../components/Button.jsx'
import { Notice } from '../../components/Notice.jsx'

export function OwnerStep({ token, onCreated }) {
  const [login, setLogin] = useState('')
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [problem, setProblem] = useState(null)

  async function submit(event) {
    event.preventDefault()
    setBusy(true)
    setProblem(null)

    try {
      await post('install/owner', { token, login, password })
      onCreated()
    } catch (e) {
      if (e.code === 'weak_password') {
        // The server joins every weakness into one string of sentences.
        setProblem({ field: 'password', list: e.message.split(/(?<=\.)\s+/).filter(Boolean) })
      } else if (e.code === 'bad_login') {
        setProblem({
          field: 'login',
          text: 'Use 3 to 64 letters, digits, dots, dashes, underscores or @, starting with a letter or digit. No spaces.',
        })
      } else if (e.code === 'owner_exists') {
        setProblem({ field: 'login', text: 'An owner already exists for this copy. Reload this page to carry on.' })
      } else {
        setProblem({ field: 'login', text: e.message })
      }
      setBusy(false)
    }
  }

  return (
    <Card title="Create the owner">
      <form class="space-y-4" onSubmit={submit}>
        <p class="text-sm leading-relaxed text-ink-muted">
          Pick a name to sign in with. You are the owner of this copy; you can add other people later.
        </p>

        <Field
          label="Name"
          value={login}
          onInput={setLogin}
          autoComplete="username"
          error={problem?.field === 'login' ? problem.text : null}
        />

        <Field label="Password" type="password" value={password} onInput={setPassword} autoComplete="new-password" />

        {problem?.field === 'password' && (
          <Notice tone="warn" title="That password needs work">
            <ul class="list-disc space-y-1 pl-5">
              {problem.list.map((line) => (
                <li key={line}>{line}</li>
              ))}
            </ul>
          </Notice>
        )}

        <Button type="submit" busy={busy} disabled={login.trim() === '' || password === ''}>
          {busy ? 'Creating…' : 'Create owner'}
        </Button>
      </form>
    </Card>
  )
}
