/**
 * Standing queue of proposals awaiting a decision.
 *
 * The assistant panel surfaces proposals inline as they happen; this is where
 * anything raised over MCP by an external client ends up, since there is no
 * conversation for those to appear in.
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { api } from '../api';
import ProposalCard from './ProposalCard';

export default function Approvals( { onChanged, onPendingChange } ) {
	const [ proposals, setProposals ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ busyId, setBusyId ] = useState( null );
	const [ error, setError ] = useState( null );

	const load = useCallback( async () => {
		setLoading( true );
		try {
			const { proposals: list } = await api.proposals( 'pending' );
			setProposals( list );
			onPendingChange?.( list.length );
			setError( null );
		} catch ( e ) {
			setError( e.message );
		} finally {
			setLoading( false );
		}
	}, [ onPendingChange ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const decide = useCallback(
		async ( proposal, approved ) => {
			setBusyId( proposal.proposal_id );
			try {
				if ( approved ) {
					const result = await api.approve( proposal.proposal_id );
					if ( ! result.ok ) {
						setError( result.error || 'The change could not be applied.' );
					}
				} else {
					await api.reject( proposal.proposal_id, 'Declined from the approvals queue.' );
				}
				await load();
				onChanged?.();
			} catch ( e ) {
				setError( e.message );
			} finally {
				setBusyId( null );
			}
		},
		[ load, onChanged ]
	);

	if ( loading ) {
		return <div className="wpsm-loading">Loading…</div>;
	}

	return (
		<div className="wpsm-approvals">
			{ error && <div className="wpsm-error">{ error }</div> }

			{ proposals.length === 0 ? (
				<div className="wpsm-empty">
					<h2>Nothing waiting</h2>
					<p>
						Changes proposed by the assistant, or by an external client over MCP, appear
						here until someone approves or declines them.
					</p>
				</div>
			) : (
				proposals.map( ( proposal ) => (
					<ProposalCard
						key={ proposal.proposal_id }
						proposal={ proposal }
						busy={ busyId === proposal.proposal_id }
						onApprove={ () => decide( proposal, true ) }
						onReject={ () => decide( proposal, false ) }
					/>
				) )
			) }
		</div>
	);
}
