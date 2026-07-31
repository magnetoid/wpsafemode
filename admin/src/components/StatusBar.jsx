/**
 * Header showing the state that changes how you should read everything else:
 * whether safe mode is on, and whether the safety chain is fully armed.
 */

export default function StatusBar( { status, onRefresh } ) {
	const safe = status.safe_mode || {};
	const safety = status.site?.safety || {};
	const dropins = status.dropins || {};

	const degraded = [];

	if ( ! ( dropins.fatal_handler?.healthy ?? false ) ) {
		degraded.push( 'automatic rollback after a fatal error' );
	}
	if ( ! ( dropins.session_overlay?.healthy ?? false ) ) {
		degraded.push( 'safe mode' );
	}
	if ( safety.lint_available === false ) {
		degraded.push( 'PHP syntax checking' );
	}
	if ( safety.verify_probe === false ) {
		degraded.push( 'post-write verification' );
	}

	return (
		<header className="wpsm-status">
			<div className="wpsm-status-main">
				<h1>Safe Mode AI Toolkit</h1>
				<p className="wpsm-status-site">
					{ status.site?.site?.name } — WordPress { status.site?.site?.wp_version } on PHP{ ' ' }
					{ status.site?.site?.php_version }
				</p>
			</div>

			<div className="wpsm-status-flags">
				{ safe.active ? (
					<span className="wpsm-pill wpsm-pill-safe">
						Safe mode on — { Math.ceil( ( safe.seconds_remaining || 0 ) / 60 ) }m left.
						Visitors are unaffected.
					</span>
				) : (
					<span className="wpsm-pill wpsm-pill-live">Live site view</span>
				) }

				{ ! status.agent?.configured && (
					<span className="wpsm-pill wpsm-pill-warn">No API key configured</span>
				) }

				{ degraded.length > 0 && (
					<span className="wpsm-pill wpsm-pill-warn" title={ degraded.join( ', ' ) }>
						Unavailable on this host: { degraded.join( ', ' ) }
					</span>
				) }

				<button className="button button-small" onClick={ onRefresh }>
					Refresh
				</button>
			</div>
		</header>
	);
}
