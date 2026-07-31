/**
 * Unified diff renderer.
 *
 * The server sends plain unified diff text. Colouring it here rather than
 * shipping markup from PHP keeps the wire format something a person can also
 * read directly in an API response.
 */

export default function Diff( { diff } ) {
	if ( ! diff || ! diff.unified ) {
		return <p className="wpsm-hint">No textual change.</p>;
	}

	const lines = diff.unified.split( '\n' );

	return (
		<div className="wpsm-diff">
			<div className="wpsm-diff-summary">
				<span className="wpsm-diff-added">+{ diff.added }</span>
				<span className="wpsm-diff-removed">−{ diff.removed }</span>
				<span className="wpsm-hint">
					{ diff.hunks } { diff.hunks === 1 ? 'hunk' : 'hunks' }
				</span>
				{ diff.truncated && <span className="wpsm-pill wpsm-pill-warn">truncated</span> }
			</div>

			<pre className="wpsm-diff-body">
				{ lines.map( ( line, i ) => (
					<div key={ i } className={ `wpsm-diff-line ${ lineClass( line ) }` }>
						{ line || ' ' }
					</div>
				) ) }
			</pre>
		</div>
	);
}

function lineClass( line ) {
	if ( line.startsWith( '+++' ) || line.startsWith( '---' ) ) {
		return 'is-meta';
	}
	if ( line.startsWith( '@@' ) ) {
		return 'is-hunk';
	}
	if ( line.startsWith( '+' ) ) {
		return 'is-add';
	}
	if ( line.startsWith( '-' ) ) {
		return 'is-del';
	}
	return '';
}
