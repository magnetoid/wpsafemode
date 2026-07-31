/**
 * The assistant panel.
 *
 * Renders the transcript, drives the agent loop, and surfaces the proposal
 * inline when the loop pauses for approval — so approving is part of the
 * conversation rather than a context switch to another tab.
 */

import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { api } from '../api';
import { useAgent } from '../useAgent';
import ProposalCard from './ProposalCard';

export default function Chat( { status, onChanged, onPendingChange } ) {
	const [ draft, setDraft ] = useState( '' );
	const bottom = useRef( null );

	const { messages, running, error, awaitingApproval, send, resume, reset, stop } = useAgent( {
		onProposal: () => onPendingChange?.( ( n ) => n + 1 ),
	} );

	useEffect( () => {
		bottom.current?.scrollIntoView( { behavior: 'smooth' } );
	}, [ messages, awaitingApproval ] );

	const submit = useCallback(
		( event ) => {
			event.preventDefault();

			const text = draft.trim();
			if ( ! text || running ) {
				return;
			}

			setDraft( '' );
			send( text );
		},
		[ draft, running, send ]
	);

	const decide = useCallback(
		async ( approved ) => {
			const pending = awaitingApproval;
			if ( ! pending ) {
				return;
			}

			if ( approved ) {
				const outcome = await api.approve( pending.proposalId );
				await resume( outcome );
			} else {
				await api.reject( pending.proposalId, 'Declined from the assistant panel.' );
				await resume( null );
			}

			onChanged?.();
		},
		[ awaitingApproval, resume, onChanged ]
	);

	if ( ! status.agent?.configured ) {
		return (
			<div className="wpsm-empty">
				<h2>No API key configured</h2>
				<p>
					Add an Anthropic API key in Settings to use the assistant. Everything else in the
					toolkit — safe mode, the file tools, the change history — works without one.
				</p>
			</div>
		);
	}

	return (
		<div className="wpsm-chat">
			<div className="wpsm-transcript">
				{ messages.length === 0 && (
					<div className="wpsm-empty">
						<h2>What needs doing?</h2>
						<p>
							The assistant can read and edit theme and plugin files, inspect the
							database, and put this session into safe mode to reproduce a conflict.
						</p>
						<p className="wpsm-hint">
							Nothing it changes is applied without you approving the exact diff first.
						</p>
					</div>
				) }

				{ messages.map( ( message, index ) => (
					<Message key={ index } message={ message } />
				) ) }

				{ awaitingApproval && (
					<ProposalCard
						proposal={ awaitingApproval }
						onApprove={ () => decide( true ) }
						onReject={ () => decide( false ) }
					/>
				) }

				{ running && <div className="wpsm-thinking">Working…</div> }
				{ error && <div className="wpsm-error">{ error }</div> }

				<div ref={ bottom } />
			</div>

			<form className="wpsm-composer" onSubmit={ submit }>
				<textarea
					value={ draft }
					onChange={ ( e ) => setDraft( e.target.value ) }
					onKeyDown={ ( e ) => {
						if ( e.key === 'Enter' && ( e.metaKey || e.ctrlKey ) ) {
							submit( e );
						}
					} }
					placeholder="Describe what you want to change or investigate…"
					rows={ 3 }
					disabled={ running || !! awaitingApproval }
				/>
				<div className="wpsm-composer-actions">
					{ running ? (
						<button type="button" className="button" onClick={ stop }>
							Stop
						</button>
					) : (
						<button type="submit" className="button button-primary" disabled={ !! awaitingApproval }>
							Send
						</button>
					) }
					{ messages.length > 0 && ! running && (
						<button type="button" className="button-link" onClick={ reset }>
							New conversation
						</button>
					) }
				</div>
			</form>
		</div>
	);
}

function Message( { message } ) {
	// A user turn carrying tool_result blocks is loop plumbing, not something
	// the operator wrote. Render it as tool output rather than as their words.
	const isToolResults =
		message.role === 'user' &&
		Array.isArray( message.content ) &&
		message.content.every( ( block ) => block.type === 'tool_result' );

	if ( isToolResults ) {
		return (
			<div className="wpsm-message wpsm-message-tool">
				{ message.content.map( ( block, i ) => (
					<details key={ i } className={ block.is_error ? 'is-error' : '' }>
						<summary>{ block.is_error ? 'Tool error' : 'Tool result' }</summary>
						<pre>{ block.content }</pre>
					</details>
				) ) }
			</div>
		);
	}

	if ( message.role === 'user' ) {
		return (
			<div className="wpsm-message wpsm-message-user">
				<p>{ typeof message.content === 'string' ? message.content : '' }</p>
			</div>
		);
	}

	return (
		<div className="wpsm-message wpsm-message-assistant">
			{ ( message.content || [] ).map( ( block, i ) => {
				if ( block.type === 'text' ) {
					return (
						<div key={ i } className="wpsm-text">
							{ block.text.split( '\n\n' ).map( ( para, p ) => (
								<p key={ p }>{ para }</p>
							) ) }
						</div>
					);
				}

				if ( block.type === 'tool_use' ) {
					return (
						<div key={ i } className="wpsm-tool-use">
							<code>{ block.name }</code>
							{ block.input?.path && <span className="wpsm-target">{ block.input.path }</span> }
							{ block.input?.sql && <span className="wpsm-target">{ block.input.sql }</span> }
						</div>
					);
				}

				// Thinking blocks arrive with empty text unless summarised
				// display is on, so there is nothing worth rendering.
				return null;
			} ) }
		</div>
	);
}
