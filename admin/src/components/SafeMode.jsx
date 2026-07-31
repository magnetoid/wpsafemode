/**
 * Safe mode control panel.
 *
 * The plugin checkboxes are the point: "turn everything off" is one preset, but
 * being able to name an exact subset is what makes this a bisect tool.
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { api, config } from '../api';

export default function SafeMode( { status, onChanged } ) {
	const [ plugins, setPlugins ] = useState( [] );
	const [ selected, setSelected ] = useState( [] );
	const [ minutes, setMinutes ] = useState( 60 );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( null );

	const active = status.safe_mode?.active;
	const overlayReady = status.dropins?.session_overlay?.healthy;

	useEffect( () => {
		api.runTool( 'safe_mode_status', {} )
			.then( ( outcome ) => {
				setPlugins( outcome.result?.available_plugins || [] );
				// live_state, not session_state: if a session is already open,
				// preselecting what the overlay shows would make "keep all"
				// mean "keep what safe mode left me", which is not what anyone
				// reading that button expects.
				setSelected( outcome.result?.live_state?.plugins || [] );
			} )
			.catch( ( e ) => setError( e.message ) );
	}, [] );

	const start = useCallback( async () => {
		setBusy( true );
		setError( null );
		try {
			const outcome = await api.runTool( 'safe_mode_start', {
				plugins: selected,
				minutes,
			} );

			if ( outcome.needs_approval ) {
				const applied = await api.approve( outcome.proposal_id );
				if ( ! applied.ok ) {
					setError( applied.error || 'Could not enter safe mode.' );
				}
			}

			onChanged?.();
		} catch ( e ) {
			setError( e.message );
		} finally {
			setBusy( false );
		}
	}, [ selected, minutes, onChanged ] );

	const stop = useCallback( async () => {
		setBusy( true );
		try {
			const outcome = await api.runTool( 'safe_mode_stop', {} );
			if ( outcome.needs_approval ) {
				await api.approve( outcome.proposal_id );
			}
			onChanged?.();
		} catch ( e ) {
			setError( e.message );
		} finally {
			setBusy( false );
		}
	}, [ onChanged ] );

	if ( ! overlayReady ) {
		return (
			<div className="wpsm-empty">
				<h2>Safe mode is not available</h2>
				<p>
					The session overlay drop-in is missing or has been modified. Reinstall it from
					Settings, then reload this page.
				</p>
			</div>
		);
	}

	return (
		<div className="wpsm-safemode">
			{ error && <div className="wpsm-error">{ error }</div> }

			<div className="wpsm-callout">
				<p>
					Safe mode changes what <strong>this browser session</strong> sees. Logged-out
					visitors, and every other logged-in user, continue to see the site exactly as it
					is. Nothing about the live site changes.
				</p>
			</div>

			{ active ? (
				<div className="wpsm-safemode-active">
					<h2>Safe mode is on</h2>
					<p>
						Expires in{ ' ' }
						{ Math.ceil( ( status.safe_mode.seconds_remaining || 0 ) / 60 ) } minutes.
					</p>
					<ul className="wpsm-file-list">
						<li>
							Plugins active for you:{ ' ' }
							{ status.safe_mode.profile?.plugins?.length || 0 }
						</li>
						{ status.safe_mode.profile?.theme && (
							<li>Theme: { status.safe_mode.profile.theme }</li>
						) }
					</ul>
					<p>
						<a href={ config.homeUrl } target="_blank" rel="noreferrer" className="button">
							View the site in this state
						</a>{ ' ' }
						<button className="button button-primary" onClick={ stop } disabled={ busy }>
							Leave safe mode
						</button>
					</p>
				</div>
			) : (
				<div className="wpsm-safemode-setup">
					<h2>Choose what stays active</h2>

					<p className="wpsm-preset-row">
						<button className="button button-small" onClick={ () => setSelected( [] ) }>
							Disable all
						</button>
						<button
							className="button button-small"
							onClick={ () => setSelected( plugins.map( ( p ) => p.file ) ) }
						>
							Keep all
						</button>
						<button
							className="button button-small"
							onClick={ () => setSelected( selected.slice( 0, Math.ceil( selected.length / 2 ) ) ) }
						>
							Halve the selection
						</button>
					</p>

					<ul className="wpsm-plugin-list">
						{ plugins.map( ( plugin ) => (
							<li key={ plugin.file }>
								<label>
									<input
										type="checkbox"
										checked={ selected.includes( plugin.file ) }
										onChange={ ( e ) =>
											setSelected( ( prev ) =>
												e.target.checked
													? [ ...prev, plugin.file ]
													: prev.filter( ( f ) => f !== plugin.file )
											)
										}
									/>
									{ plugin.name }
								</label>
							</li>
						) ) }
					</ul>

					<p>
						<label>
							Session length
							<input
								type="number"
								min="1"
								max="480"
								value={ minutes }
								onChange={ ( e ) => setMinutes( Number( e.target.value ) ) }
							/>
							minutes
						</label>
					</p>

					<button className="button button-primary" onClick={ start } disabled={ busy }>
						{ busy ? 'Starting…' : 'Enter safe mode' }
					</button>
				</div>
			) }
		</div>
	);
}
