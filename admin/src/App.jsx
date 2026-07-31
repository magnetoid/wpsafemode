/**
 * Toolkit shell: status header plus tabbed panels.
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { api } from './api';
import Chat from './components/Chat';
import Changes from './components/Changes';
import SafeMode from './components/SafeMode';
import Settings from './components/Settings';
import Approvals from './components/Approvals';
import StatusBar from './components/StatusBar';

const TABS = [
	{ id: 'chat', label: 'Assistant' },
	{ id: 'approvals', label: 'Approvals' },
	{ id: 'changes', label: 'Changes' },
	{ id: 'safemode', label: 'Safe Mode' },
	{ id: 'settings', label: 'Settings' },
];

export default function App() {
	const [ tab, setTab ] = useState( 'chat' );
	const [ status, setStatus ] = useState( null );
	const [ pendingCount, setPendingCount ] = useState( 0 );
	const [ loadError, setLoadError ] = useState( null );

	const refresh = useCallback( async () => {
		try {
			const next = await api.status();
			setStatus( next );
			setPendingCount( next.proposals || 0 );
			setLoadError( null );
		} catch ( e ) {
			setLoadError( e.message );
		}
	}, [] );

	useEffect( () => {
		refresh();
	}, [ refresh ] );

	if ( loadError ) {
		return (
			<div className="wpsm-fatal">
				<h1>WP Safe Mode AI Toolkit</h1>
				<p>Could not load the toolkit status.</p>
				<pre>{ loadError }</pre>
				<button className="button" onClick={ refresh }>
					Try again
				</button>
			</div>
		);
	}

	if ( ! status ) {
		return <div className="wpsm-loading">Loading…</div>;
	}

	return (
		<div className="wpsm">
			<StatusBar status={ status } onRefresh={ refresh } />

			<nav className="wpsm-tabs" role="tablist">
				{ TABS.map( ( item ) => (
					<button
						key={ item.id }
						role="tab"
						aria-selected={ tab === item.id }
						className={ `wpsm-tab ${ tab === item.id ? 'is-active' : '' }` }
						onClick={ () => setTab( item.id ) }
					>
						{ item.label }
						{ item.id === 'approvals' && pendingCount > 0 && (
							<span className="wpsm-badge">{ pendingCount }</span>
						) }
					</button>
				) ) }
			</nav>

			<div className="wpsm-panel" role="tabpanel">
				{ tab === 'chat' && (
					<Chat
						status={ status }
						onChanged={ refresh }
						onPendingChange={ setPendingCount }
					/>
				) }
				{ tab === 'approvals' && (
					<Approvals onChanged={ refresh } onPendingChange={ setPendingCount } />
				) }
				{ tab === 'changes' && <Changes onChanged={ refresh } /> }
				{ tab === 'safemode' && <SafeMode status={ status } onChanged={ refresh } /> }
				{ tab === 'settings' && <Settings onChanged={ refresh } /> }
			</div>
		</div>
	);
}
