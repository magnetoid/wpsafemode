import { useEffect, useState } from 'preact/hooks'
import { get, post, setCsrf } from './api.js'
import { Install } from './screens/Install.jsx'
import { Login } from './screens/Login.jsx'
import { Environment } from './screens/Environment.jsx'

// install/state first. Not installed -> the wizard. Installed -> auth/me: a
// 401 means sign in; a 200 means the environment panel. Nothing here asks for
// api/env before sign-in.
export function App() {
  const [view, setView] = useState({ name: 'loading' })
  const [signOutError, setSignOutError] = useState(null)

  useEffect(() => {
    async function route() {
      try {
        const state = await get('install/state')

        if (!state.installed) {
          setView({ name: 'install', state })
          return
        }

        try {
          const me = await get('auth/me')
          setCsrf(me.csrf)
          setView({ name: 'panel', me, selfTest: null })
        } catch (e) {
          if (e.status === 401) {
            setView({ name: 'login' })
            return
          }
          throw e
        }
      } catch (e) {
        setView({ name: 'error', message: e.message })
      }
    }

    route()
  }, [])

  async function signOut() {
    setSignOutError(null)

    try {
      await post('auth/logout', {})
    } catch (e) {
      // Still signed in as far as the server is concerned: say so, stay here.
      setSignOutError("Sign out didn't work, so you are still signed in. " + e.message)
      return
    }

    setCsrf(null)
    setView({ name: 'login' })
  }

  if (view.name === 'install') {
    return <Install initialState={view.state} onInstalled={() => setView({ name: 'login' })} />
  }

  if (view.name === 'login') {
    return (
      <Login
        onSignedIn={(body) =>
          setView({ name: 'panel', me: { user: body.user, csrf: body.csrf }, selfTest: body.self_test })
        }
      />
    )
  }

  if (view.name === 'panel') {
    return <Environment me={view.me} selfTest={view.selfTest} notice={signOutError} onSignOut={signOut} />
  }

  if (view.name === 'error') {
    return (
      <main class="min-h-screen bg-surface-sunken px-6 py-12 font-sans text-ink">
        <div class="mx-auto max-w-xl rounded-card border border-danger/30 bg-surface p-6 shadow-card">
          <p class="font-medium">Can't reach the server</p>
          <p class="mt-2 text-sm text-ink-muted">
            The page loaded but the tool's own PHP did not answer ({view.message}). Nothing here reflects the state of
            your site.
          </p>
        </div>
      </main>
    )
  }

  return <p class="p-6 text-sm text-ink-faint">Checking this server…</p>
}
