/**
 * Entry point for the toolkit admin screen.
 */

import { createRoot } from '@wordpress/element';
import App from './App';
import './style.scss';

const container = document.getElementById( 'wpsm-root' );

if ( container ) {
	createRoot( container ).render( <App /> );
}
