import { Notice } from './Notice.jsx'
import { installPath } from '../api.js'

// The nginx rule for this copy's own folder. nginx matches the decoded path
// against a regex, so the folder is regex-escaped and the whole pattern
// quoted, which keeps a folder name with a space or brackets working.
export function nginxRule(path) {
  const escaped = path.replace(/[.*+?^${}()|[\]\\"]/g, '\\$&')

  return 'location ~ "^' + escaped + 'data/" { deny all; return 404; }'
}

// Shown by the installer and again after sign-in: a host change can expose
// the data folder long after setup.
export function ExposureNotice({ detail }) {
  return (
    <Notice tone="danger" title="This tool's data folder can be opened from the web">
      <p>{detail}</p>
      <p class="mt-2">If your server is nginx, add this and reload it:</p>
      <pre class="mt-1 overflow-x-auto rounded-panel bg-surface p-2 font-mono text-xs text-ink">
        {nginxRule(installPath())}
      </pre>
    </Notice>
  )
}
