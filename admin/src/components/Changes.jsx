/**
 * Change history, with one-click rollback.
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { api } from '../api';

const STATUS_LABELS = {
	pending: { label: 'Applied, not yet verified', tone: 'warn' },
	committed: { label: 'Applied', tone: 'ok' },
	reverted: { label: 'Rolled back', tone: 'muted' },
	auto_reverted: { label: 'Rolled back automatically', tone: 'warn' },
	failed: { label: 'Never applied', tone: 'muted' },
};

export default function Changes( { onChanged } ) {
	const [ changes, setChanges ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ busy, setBusy ] = useState( null );
	const [ error, setError ] = useState( null );

	const load = useCallback( async () => {
		setLoading( true );
		try {
			const { changes: list } = await api.journal( { limit: 100 } );
			setChanges( list );
			setError( null );
		} catch ( e ) {
			setError( e.message );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const revert = useCallback(
		async ( changeId ) => {
			setBusy( changeId );
			try {
				// Goes through the tool registry, so a rollback is proposed and
				// approved exactly like any other change rather than being a
				// privileged shortcut.
				const outcome = await api.runTool( 'revert_change', { change_id: changeId } );

				if ( outcome.needs_approval ) {
					const applied = await api.approve( outcome.proposal_id );
					if ( ! applied.ok ) {
						setError( applied.error || 'Rollback failed.' );
					}
				}

				await load();
				onChanged?.();
			} catch ( e ) {
				setError( e.message );
			} finally {
				setBusy( null );
			}
		},
		[ load, onChanged ]
	);

	if ( loading ) {
		return <div className="wpsm-loading">Loading…</div>;
	}

	return (
		<div className="wpsm-changes">
			{ error && <div className="wpsm-error">{ error }</div> }

			{ changes.length === 0 ? (
				<div className="wpsm-empty">
					<h2>No changes yet</h2>
					<p>Every file the toolkit writes is recorded here, with its snapshot.</p>
				</div>
			) : (
				<table className="widefat striped">
					<thead>
						<tr>
							<th>When</th>
							<th>What</th>
							<th>Status</th>
							<th />
						</tr>
					</thead>
					<tbody>
						{ changes.map( ( change ) => {
							const status = STATUS_LABELS[ change.status ] || {
								label: change.status,
								tone: 'muted',
							};
							const revertible = [ 'committed', 'pending' ].includes( change.status );

							return (
								<tr key={ change.change_id }>
									<td>
										<time dateTime={ change.created_at }>
											{ change.created_at } UTC
										</time>
									</td>
									<td>
										<code>{ change.target }</code>
										<div className="wpsm-hint">
											{ change.op }
											{ change.meta?.reverted_by === 'fatal_error_handler' &&
												' — reverted by the fatal-error handler' }
										</div>
									</td>
									<td>
										<span className={ `wpsm-pill wpsm-pill-${ status.tone }` }>
											{ status.label }
										</span>
									</td>
									<td>
										{ revertible && (
											<button
												className="button button-small"
												onClick={ () => revert( change.change_id ) }
												disabled={ busy === change.change_id }
											>
												{ busy === change.change_id ? 'Reverting…' : 'Revert' }
											</button>
										) }
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
