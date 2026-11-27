import { useState } from 'preact/hooks'
import { Pill } from '../components/Pill.jsx'
import { TokenStep } from './install/TokenStep.jsx'
import { OwnerStep } from './install/OwnerStep.jsx'
import { KeyStep } from './install/KeyStep.jsx'
import { MoveStep } from './install/MoveStep.jsx'

const LABELS = { token: 'Token', owner: 'Owner', key: 'Key', move: 'Move' }

// token -> owner -> key (self-test first) -> move -> finish.
// The verified token lives in this component's memory only. It is never
// written to storage or the address bar, so a reload asks for it again.
export function Install({ initialState, onInstalled }) {
  const state = initialState
  const [token, setToken] = useState(null)
  const [phase, setPhase] = useState('token')

  function verified(value) {
    setToken(value)
    setPhase(state.has_owner ? 'key' : 'owner')
  }

  return (
    <main class="min-h-screen bg-surface-sunken px-6 py-12 font-sans text-ink antialiased">
      <div class="mx-auto max-w-xl">
        <header class="mb-8">
          <h1 class="font-display text-3xl font-semibold tracking-tight">WP SafeMode</h1>
          <div class="mt-3 flex flex-wrap gap-2" aria-label="Setup progress">
            {Object.entries(LABELS).map(([id, label]) => (
              <Pill key={id} tone={id === phase ? 'good' : 'idle'}>
                {label}
              </Pill>
            ))}
          </div>
        </header>

        {phase === 'token' && <TokenStep hasWordPress={state.has_wordpress} onVerified={verified} />}
        {phase === 'owner' && <OwnerStep token={token} onCreated={() => setPhase('key')} />}
        {phase === 'key' && <KeyStep token={token} onStored={() => setPhase('move')} />}
        {phase === 'move' && <MoveStep token={token} onFinished={onInstalled} />}
      </div>
    </main>
  )
}
