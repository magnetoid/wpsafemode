/**
 * The agent loop.
 *
 * This runs in the browser, not in PHP. A multi-turn loop inside one PHP
 * request would hit max_execution_time on most WordPress hosts, and driving it
 * from here means every server request is one short unit of work: either a
 * single Claude call, or a single tool execution.
 *
 * It also puts the approval gate exactly where it belongs. When a tool needs a
 * human, the loop stops with the proposal in hand and waits — there is no
 * background process to interrupt and no request sitting open while someone
 * decides.
 */

import { useCallback, useRef, useState } from '@wordpress/element';
import { api } from './api';

const MAX_TURNS = 24;

/**
 * @param {Function} onProposal Called when the loop pauses for approval.
 */
export function useAgent( { onProposal } = {} ) {
	const [ messages, setMessages ] = useState( [] );
	const [ running, setRunning ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ awaitingApproval, setAwaitingApproval ] = useState( null );

	// The loop reads and writes conversation state many times per turn, so it
	// works against a ref and mirrors into state for rendering. Driving it from
	// state directly would mean every tool result waiting for a re-render.
	const transcript = useRef( [] );
	const abortRef = useRef( null );

	const sync = useCallback( () => {
		setMessages( [ ...transcript.current ] );
	}, [] );

	const reset = useCallback( () => {
		abortRef.current?.abort();
		transcript.current = [];
		setMessages( [] );
		setError( null );
		setAwaitingApproval( null );
		setRunning( false );
	}, [] );

	const stop = useCallback( () => {
		abortRef.current?.abort();
		setRunning( false );
	}, [] );

	/**
	 * Run turns until Claude stops asking for tools, a tool needs approval, or
	 * we hit the turn ceiling.
	 */
	const drive = useCallback(
		async () => {
			setRunning( true );
			setError( null );

			const controller = new AbortController();
			abortRef.current = controller;

			try {
				for ( let turn = 0; turn < MAX_TURNS; turn++ ) {
					const { message } = await api.agentTurn( transcript.current, controller.signal );

					transcript.current.push( { role: 'assistant', content: message.content } );
					sync();

					// A refusal is HTTP 200 with nothing usable in it. Say so
					// rather than rendering an empty assistant turn.
					if ( message.stop_reason === 'refusal' ) {
						setError(
							'Claude declined this request' +
								( message.stop_details?.category
									? ` (${ message.stop_details.category })`
									: '' ) +
								'. Rephrasing usually helps; the toolkit sits close enough to the security tooling line to trip a classifier occasionally.'
						);
						break;
					}

					const toolUses = ( message.content || [] ).filter( ( b ) => b.type === 'tool_use' );

					if ( toolUses.length === 0 ) {
						break;
					}

					const results = [];
					let paused = false;

					for ( const use of toolUses ) {
						const outcome = await api.runTool( use.name, use.input || {} ).catch( ( e ) => ( {
							ok: false,
							error: e.message,
						} ) );

						if ( outcome.needs_approval ) {
							// Park the loop. The tool_result for this call is
							// produced later, by resume(), once a human has
							// decided.
							const pending = {
								toolUseId: use.id,
								toolName: use.name,
								proposalId: outcome.proposal_id,
								preview: outcome.preview,
								// Any sibling calls in the same assistant turn
								// still need results, or the API rejects the
								// follow-up for having an unanswered tool_use.
								siblings: results,
								remaining: toolUses.slice( toolUses.indexOf( use ) + 1 ),
							};

							setAwaitingApproval( pending );
							onProposal?.( pending );
							paused = true;
							break;
						}

						results.push( toolResult( use.id, outcome ) );
					}

					if ( paused ) {
						return;
					}

					transcript.current.push( { role: 'user', content: results } );
					sync();
				}
			} catch ( e ) {
				if ( e.name !== 'AbortError' ) {
					setError( e.message );
				}
			} finally {
				setRunning( false );
				abortRef.current = null;
			}
		},
		[ onProposal, sync ]
	);

	const send = useCallback(
		async ( text ) => {
			transcript.current.push( { role: 'user', content: text } );
			sync();
			await drive();
		},
		[ drive, sync ]
	);

	/**
	 * Continue after a proposal was approved or rejected.
	 *
	 * @param {Object|null} outcome Result from approving, or null if rejected.
	 */
	const resume = useCallback(
		async ( outcome ) => {
			const pending = awaitingApproval;

			if ( ! pending ) {
				return;
			}

			setAwaitingApproval( null );

			const results = [ ...pending.siblings ];

			results.push(
				outcome
					? toolResult( pending.toolUseId, outcome )
					: {
							type: 'tool_result',
							tool_use_id: pending.toolUseId,
							is_error: true,
							content: 'The person reviewing this declined the change. Do not retry it — ask what they would prefer instead.',
					  }
			);

			// Anything the model asked for after the gated call never ran.
			// Tell it so, rather than leaving those tool_use blocks unanswered.
			for ( const skipped of pending.remaining ) {
				results.push( {
					type: 'tool_result',
					tool_use_id: skipped.id,
					is_error: true,
					content: 'Not run: the loop paused for approval of an earlier call in this turn.',
				} );
			}

			transcript.current.push( { role: 'user', content: results } );
			sync();

			await drive();
		},
		[ awaitingApproval, drive, sync ]
	);

	return {
		messages,
		running,
		error,
		awaitingApproval,
		send,
		resume,
		reset,
		stop,
	};
}

function toolResult( id, outcome ) {
	return {
		type: 'tool_result',
		tool_use_id: id,
		is_error: ! outcome.ok,
		content: JSON.stringify( outcome.ok ? outcome.result : { error: outcome.error }, null, 2 ),
	};
}
