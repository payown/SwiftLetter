/* SwiftLetter — Settings page: Test API Key */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function () {
		document.querySelectorAll( '.swl-test-api-btn' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var provider   = btn.dataset.provider;
				var resultSpan = btn.parentElement.querySelector( '.swl-test-api-result' );

				if ( ! provider || ! resultSpan ) {
					return;
				}

				btn.disabled         = true;
				resultSpan.textContent = swlSettings.i18n.testing;
				resultSpan.style.color = '';
				resultSpan.style.fontWeight = '600';

				fetch( swlSettings.restUrl + 'settings/test-api-key', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce':   swlSettings.nonce,
					},
					body: JSON.stringify( { provider: provider } ),
				} )
					.then( function ( response ) {
						return response.json();
					} )
					.then( function ( data ) {
						if ( data.ok ) {
							resultSpan.textContent  = '\u2713 ' + data.message;
							resultSpan.style.color  = '#00a32a';
						} else {
							resultSpan.textContent  = '\u2717 ' + data.message;
							resultSpan.style.color  = '#d63638';
						}
					} )
					.catch( function () {
						resultSpan.textContent = '\u2717 ' + swlSettings.i18n.requestFailed;
						resultSpan.style.color = '#d63638';
					} )
					.finally( function () {
						btn.disabled = false;
					} );
			} );
		} );
	} );
}() );
