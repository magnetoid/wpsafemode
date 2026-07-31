/**
 * Settings: credentials, model, and the switches that control how much the
 * toolkit is allowed to do without asking.
 */

import { useCallback, useEffect, useState } from '@wordpress/element';
import { api } from '../api';

export default function Settings( { onChanged } ) {
	const [ settings, setSettings ] = useState( null );
	const [ apiKey, setApiKey ] = useState( '' );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );
	const [ error, setError ] = useState( null );

	const load = useCallback( async () => {
		try {
			setSettings( await api.settings() );
		} catch ( e ) {
			setError( e.message );
		}
	}, [] );

	useEffect( () => {
		load();
	}, [ load ] );

	const save = useCallback(
		async ( patch ) => {
			setSaving( true );
			setNotice( null );
			try {
				const next = await api.saveSettings( patch );
				setSettings( next );
				setApiKey( '' );
				setNotice( 'Saved.' );
				onChanged?.();
			} catch ( e ) {
				setError( e.message );
			} finally {
				setSaving( false );
			}
		},
		[ onChanged ]
	);

	const reinstall = useCallback( async () => {
		setSaving( true );
		try {
			const result = await api.installDropins();
			const skipped = result.install?.skipped || [];
			const errors = result.install?.errors || [];

			setNotice(
				errors.length || skipped.length
					? [ ...errors, ...skipped.map( ( s ) => `${ s.file }: ${ s.reason }` ) ].join( ' ' )
					: 'Drop-ins reinstalled.'
			);
			onChanged?.();
		} catch ( e ) {
			setError( e.message );
		} finally {
			setSaving( false );
		}
	}, [ onChanged ] );

	if ( ! settings ) {
		return <div className="wpsm-loading">Loading…</div>;
	}

	return (
		<div className="wpsm-settings">
			{ error && <div className="wpsm-error">{ error }</div> }
			{ notice && <div className="wpsm-notice">{ notice }</div> }

			<section>
				<h2>Anthropic API key</h2>
				{ settings.api_key_external ? (
					<p className="wpsm-hint">
						Supplied by the environment (ANTHROPIC_API_KEY or WPSM_ANTHROPIC_API_KEY).
						That is the better arrangement — the key never touches the database.
					</p>
				) : (
					<>
						<p className="wpsm-hint">
							{ settings.api_key_set
								? `A key ending ${ settings.api_key_hint } is stored, encrypted with this site's salts.`
								: 'No key stored.' }
						</p>
						<p>
							<input
								type="password"
								value={ apiKey }
								onChange={ ( e ) => setApiKey( e.target.value ) }
								placeholder="sk-ant-…"
								autoComplete="off"
								size={ 40 }
							/>{ ' ' }
							<button
								className="button"
								onClick={ () => save( { api_key: apiKey } ) }
								disabled={ saving || ! apiKey }
							>
								Save key
							</button>
							{ settings.api_key_set && (
								<button
									className="button-link"
									onClick={ () => save( { api_key: '' } ) }
									disabled={ saving }
								>
									Remove
								</button>
							) }
						</p>
					</>
				) }
			</section>

			<section>
				<h2>Model</h2>
				<p>
					<label>
						Model{ ' ' }
						<input
							type="text"
							value={ settings.model }
							onChange={ ( e ) => setSettings( { ...settings, model: e.target.value } ) }
							onBlur={ () => save( { model: settings.model } ) }
							size={ 24 }
						/>
					</label>
				</p>
				<p>
					<label>
						Effort{ ' ' }
						<select
							value={ settings.effort }
							onChange={ ( e ) => save( { effort: e.target.value } ) }
						>
							{ [ 'low', 'medium', 'high', 'xhigh', 'max' ].map( ( level ) => (
								<option key={ level } value={ level }>
									{ level }
								</option>
							) ) }
						</select>
					</label>
					<span className="wpsm-hint">
						{ ' ' }
						xhigh suits multi-file work; low and medium are noticeably cheaper and still
						strong on focused edits.
					</span>
				</p>
			</section>

			<section>
				<h2>Safety</h2>

				<Toggle
					checked={ settings.verify_probe }
					onChange={ ( v ) => save( { verify_probe: v } ) }
					label="Fetch the site after each write to confirm it still responds"
					hint="Turn this off only if the site cannot reach itself over HTTP. Without it, a change that breaks the site is caught by the fatal-error handler rather than immediately."
				/>

				<Toggle
					checked={ settings.allow_uploads_writes }
					onChange={ ( v ) => save( { allow_uploads_writes: v } ) }
					label="Allow writes inside wp-content/uploads"
					hint="Off by default. Uploads is served publicly, so a writable-by-agent uploads directory widens what a mistake can do."
				/>

				<Toggle
					checked={ settings.mcp_expose_writes }
					onChange={ ( v ) => save( { mcp_expose_writes: v } ) }
					label="Expose change-making tools over MCP"
					hint="Off by default. External MCP clients can still only queue proposals for approval — but this widens who can put things in that queue."
				/>
			</section>

			<section>
				<h2>Drop-ins</h2>
				<p className="wpsm-hint">
					Safe mode and automatic rollback are implemented by two files outside this
					plugin. Reinstall them if they are reported as missing or modified.
				</p>
				<button className="button" onClick={ reinstall } disabled={ saving }>
					Reinstall drop-ins
				</button>
			</section>

			<section>
				<h2>Extra instructions for the assistant</h2>
				<p className="wpsm-hint">
					Appended to the system prompt. Use it for conventions specific to this site —
					coding style, which files are off limits, who to ask before touching what.
				</p>
				<textarea
					rows={ 6 }
					value={ settings.system_prompt_extra }
					onChange={ ( e ) =>
						setSettings( { ...settings, system_prompt_extra: e.target.value } )
					}
					onBlur={ () => save( { system_prompt_extra: settings.system_prompt_extra } ) }
				/>
			</section>
		</div>
	);
}

function Toggle( { checked, onChange, label, hint } ) {
	return (
		<div className="wpsm-toggle">
			<label>
				<input
					type="checkbox"
					checked={ !! checked }
					onChange={ ( e ) => onChange( e.target.checked ) }
				/>
				{ label }
			</label>
			{ hint && <p className="wpsm-hint">{ hint }</p> }
		</div>
	);
}
