/**
 * Thin REST client for the toolkit.
 *
 * Everything the UI does goes through here, so there is one place that knows
 * about the nonce, one place that turns a non-2xx into a thrown Error with a
 * readable message, and one place to change if the transport ever moves.
 */

const bootstrap = window.WPSM_BOOTSTRAP || {};

export const config = bootstrap;

class ApiError extends Error {
	constructor( message, status, payload ) {
		super( message );
		this.name = 'ApiError';
		this.status = status;
		this.payload = payload;
	}
}

async function request( path, { method = 'GET', body, signal } = {} ) {
	const response = await fetch( `${ bootstrap.restUrl }${ path }`, {
		method,
		signal,
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/json',
			'X-WP-Nonce': bootstrap.nonce,
		},
		body: body === undefined ? undefined : JSON.stringify( body ),
	} );

	const text = await response.text();
	let payload = null;

	try {
		payload = text ? JSON.parse( text ) : null;
	} catch {
		// A non-JSON body from a REST route almost always means a PHP fatal or
		// an HTML error page from the host. Surface the start of it — that is
		// far more useful than "unexpected token <".
		throw new ApiError(
			`The server returned something that was not JSON (HTTP ${ response.status }): ${ text.slice( 0, 300 ) }`,
			response.status,
			null
		);
	}

	if ( ! response.ok ) {
		throw new ApiError(
			payload?.error || payload?.message || `Request failed with HTTP ${ response.status }`,
			response.status,
			payload
		);
	}

	return payload;
}

export const api = {
	status: () => request( '/status' ),
	tools: () => request( '/tools' ),
	runTool: ( name, input ) => request( `/tools/${ name }`, { method: 'POST', body: input } ),

	proposals: ( status = 'pending' ) => request( `/proposals?status=${ encodeURIComponent( status ) }` ),
	approve: ( id ) => request( `/proposals/${ id }/approve`, { method: 'POST' } ),
	reject: ( id, reason = '' ) => request( `/proposals/${ id }/reject`, { method: 'POST', body: { reason } } ),

	journal: ( params = {} ) => {
		const query = new URLSearchParams( params ).toString();
		return request( `/journal${ query ? `?${ query }` : '' }` );
	},
	audit: ( params = {} ) => {
		const query = new URLSearchParams( params ).toString();
		return request( `/audit${ query ? `?${ query }` : '' }` );
	},

	settings: () => request( '/settings' ),
	saveSettings: ( values ) => request( '/settings', { method: 'POST', body: values } ),
	installDropins: () => request( '/dropins/install', { method: 'POST' } ),

	agentTurn: ( messages, signal ) =>
		request( '/agent/turn', { method: 'POST', body: { messages }, signal } ),
};

export { ApiError };
