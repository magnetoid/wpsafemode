// The tool lives in a randomly named directory and, on a host with no URL
// rewriting, is only reachable as <install-dir>/index.php/... So every request
// is rooted at the install directory worked out from the address bar, never
// at "/" and never relative to whatever the document happens to be.
function deriveRoot() {
  const path = location.pathname
  const script = path.indexOf('/index.php')

  return script >= 0 ? path.slice(0, script) + '/' : path.slice(0, path.lastIndexOf('/') + 1)
}

let installRoot = deriveRoot()
let csrf = null

export function setCsrf(token) {
  csrf = token
}

// After the folder is renamed the old root is gone: later calls must use the new one.
export function setBase(root) {
  installRoot = root
}

// The install folder's path as the web server sees it (decoded), for example
// "/wpsm-3f9a.../" or "/wp safe mode/".
export function installPath() {
  try {
    return decodeURIComponent(installRoot)
  } catch (e) {
    return installRoot
  }
}

// The install folder's own name, the last segment of installPath().
export function installFolder() {
  const parts = installPath().split('/').filter(Boolean)

  return parts.length > 0 ? parts[parts.length - 1] : ''
}

// Resolves a server `redirect` (../<name>/) against the install root and
// returns the new root path plus the address of its shell.
export function addressFor(redirect, query = '') {
  const root = new URL(redirect, location.origin + installRoot).pathname

  return { root, href: location.origin + root + 'index.php/' + query }
}

// Plain sentences for every code the server can send. A code is never copy.
const MESSAGES = {
  bad_csrf: 'Your sign-in changed in another tab. Reload the page to carry on.',
  already_installed: 'This copy is already set up. Reload the page to sign in.',
  no_base_url: "The check could not work out this site's address.",
  bad_token: "That token doesn't match. Copy it again from data/install-token.php.",
  storage_unavailable: "The tool could not open its own storage. Check that its data folder is writable, then reload the page.",
  unauthenticated: 'You are signed out. Reload the page and sign in again.',
  forbidden: 'Your account is not allowed to do that.',
  not_installed: 'Setup is not finished yet. Reload the page to carry on with it.',
  corrupt_install: "This copy's settings could not be read. Restore its data folder, or set it up again.",
  channel_not_allowed: 'That action is only available from the browser.',
  bad_request: 'The server could not read that request. Reload the page and try again.',
}
const FALLBACK = 'Something went wrong on the server. Reload the page and try again.'

async function request(path, options = {}) {
  const response = await fetch(installRoot + 'index.php/api/' + path, {
    credentials: 'same-origin',
    ...options,
    headers: {
      'Content-Type': 'application/json',
      ...(csrf ? { 'X-WPSM-CSRF': csrf } : {}),
      ...(options.headers || {}),
    },
  })

  const text = await response.text()
  let body = null

  try {
    body = JSON.parse(text)
  } catch (e) {
    // Fall through: an unreadable answer is an error, never an empty success.
  }

  if (body === null || typeof body !== 'object') {
    body = {}

    if (response.ok) {
      const error = new Error(FALLBACK)
      error.status = response.status
      throw error
    }
  }

  if (!response.ok) {
    const error = new Error(body.message || MESSAGES[body.error] || FALLBACK)
    error.code = body.error
    error.status = response.status
    error.retryAfter = body.retry_after ?? 0
    // weak_password lists each problem on its own.
    error.weaknesses = Array.isArray(body.weaknesses) ? body.weaknesses.filter((w) => typeof w === 'string') : []
    throw error
  }

  return body
}

export const get = (path) => request(path)
export const post = (path, body) => request(path, { method: 'POST', body: JSON.stringify(body ?? {}) })
