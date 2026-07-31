/**
 * The approval gate, rendered.
 *
 * This is the screen that decides whether the whole safety model is real or
 * theatre, so it leads with what actually changes and refuses to let a
 * lint-failing edit be approved at all.
 */

import Diff from './Diff';

export default function ProposalCard( { proposal, onApprove, onReject, busy } ) {
	const preview = proposal.preview || {};
	const blocked = !! preview.blocked;

	return (
		<div className={ `wpsm-proposal ${ blocked ? 'is-blocked' : '' }` }>
			<header className="wpsm-proposal-head">
				<h3>{ headline( preview, proposal ) }</h3>
				<code className="wpsm-proposal-id">{ proposal.proposalId || proposal.proposal_id }</code>
			</header>

			{ preview.reason && <p className="wpsm-proposal-reason">{ preview.reason }</p> }

			{ preview.kind === 'file_write' && (
				<>
					<dl className="wpsm-facts">
						<div>
							<dt>File</dt>
							<dd>
								<code>{ preview.path }</code>
							</dd>
						</div>
						<div>
							<dt>Action</dt>
							<dd>{ preview.creating ? 'Create new file' : 'Replace contents' }</dd>
						</div>
						<div>
							<dt>Size</dt>
							<dd>
								{ preview.bytes_before } → { preview.bytes_after } bytes
							</dd>
						</div>
						<div>
							<dt>Syntax check</dt>
							<dd>
								{ preview.lint?.ok ? (
									<span className="wpsm-ok">passed ({ preview.lint.strategy })</span>
								) : (
									<span className="wpsm-bad">{ preview.lint?.message }</span>
								) }
							</dd>
						</div>
					</dl>
					<Diff diff={ preview.diff } />
				</>
			) }

			{ preview.kind === 'file_delete' && (
				<dl className="wpsm-facts">
					<div>
						<dt>File</dt>
						<dd>
							<code>{ preview.path }</code>
						</dd>
					</div>
					<div>
						<dt>Size</dt>
						<dd>{ preview.bytes } bytes</dd>
					</div>
					<div>
						<dt>Reversible</dt>
						<dd>Yes — snapshotted before deletion</dd>
					</div>
				</dl>
			) }

			{ preview.kind === 'safe_mode_start' && (
				<>
					<p>
						This changes what <strong>you</strong> see. Logged-out visitors and other
						users continue to see the site exactly as it is now.
					</p>
					<dl className="wpsm-facts">
						<div>
							<dt>Will disable</dt>
							<dd>
								{ preview.will_disable?.length
									? preview.will_disable.join( ', ' )
									: 'nothing' }
							</dd>
						</div>
						{ preview.theme_change && (
							<div>
								<dt>Theme</dt>
								<dd>
									{ preview.theme_change.from } → { preview.theme_change.to }
								</dd>
							</div>
						) }
						<div>
							<dt>Expires after</dt>
							<dd>{ preview.minutes } minutes</dd>
						</div>
					</dl>
				</>
			) }

			{ preview.kind === 'revert' && (
				<>
					<p>
						Restoring the snapshot taken before <code>{ preview.target }</code> was
						changed.
					</p>
					<ul className="wpsm-file-list">
						{ ( preview.files || [] ).map( ( file, i ) => (
							<li key={ i }>
								<code>{ file.path }</code> — { file.action }
							</li>
						) ) }
					</ul>
				</>
			) }

			{ blocked && (
				<p className="wpsm-error">
					{ preview.error ||
						'This cannot be applied as proposed. Ask the assistant to correct it.' }
				</p>
			) }

			<footer className="wpsm-proposal-actions">
				<button
					className="button button-primary"
					onClick={ onApprove }
					disabled={ blocked || busy }
				>
					Approve and apply
				</button>
				<button className="button" onClick={ onReject } disabled={ busy }>
					Decline
				</button>
			</footer>
		</div>
	);
}

function headline( preview, proposal ) {
	switch ( preview.kind ) {
		case 'file_write':
			return preview.creating ? `Create ${ preview.path }` : `Edit ${ preview.path }`;
		case 'file_delete':
			return `Delete ${ preview.path }`;
		case 'safe_mode_start':
			return 'Enter safe mode';
		case 'safe_mode_stop':
			return 'Leave safe mode';
		case 'revert':
			return `Roll back ${ preview.target || preview.change_id }`;
		default:
			return proposal.toolName || proposal.tool || 'Pending change';
	}
}
