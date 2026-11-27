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

// Resolves a server `redirect` (../<name>/) against the install root and
// returns the new root path plus the address of its shell.
export function addressFor(redirect, query = '') {
  const root = new URL(redirect, location.origin + installRoot).pathname

  return { root, href: location.origin + root + 'index.php/' + query }
}

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
      const error = new Error('The server sent an answer this page could not read.')
      error.status = response.status
      throw error
    }
  }

  if (!response.ok) {
    const error = new Error(body.message || body.error || `HTTP ${response.status}`)
    error.code = body.error
    error.status = response.status
    error.retryAfter = body.retry_after ?? 0
    throw error
  }

  return body
}

export const get = (path) => request(path)
export const post = (path, body) => request(path, { method: 'POST', body: JSON.stringify(body ?? {}) })
