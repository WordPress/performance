/* eslint no-var: "off", camelcase: "off" */
/* global jQuery:false */

( function ( $, document ) {
	$( document ).ajaxComplete( function ( event, xhr, settings ) {
		// Check if this is the 'install-plugin' request.
		if (
			settings.data &&
			typeof settings.data === 'string' &&
			settings.data.includes( 'action=install-plugin' )
		) {
			const params = new URLSearchParams( settings.data );
			const slug = params.get( 'slug' );

			// Check if 'slug' was found and output the value.
			if ( ! slug ) {
				return;
			}

			const target_element = $(
				'.wpp-standalone-plugins a[data-slug="' + slug + '"]'
			);
			if ( ! target_element ) {
				return;
			}

			/*
			 * WordPress core uses a 1s timeout for updating the activation link,
			 * so we set a 1.5 timeout here to ensure our changes get updated after
			 * the core changes have taken place.
			 */
			setTimeout( function () {
				const plugin_url = target_element.attr( 'href' );
				if ( ! plugin_url ) {
					return;
				}
				const nonce = target_element.attr(
					'data-plugin-activation-nonce'
				);
				const plugin_slug = target_element.attr( 'data-slug' );
				const url = new URL( plugin_url );
				url.searchParams.set( 'action', 'perflab_activate_plugin' );
				url.searchParams.set( '_wpnonce', nonce );
				url.searchParams.set( 'plugin', plugin_slug );
				target_element.attr( 'href', url.href );
			}, 1500 );
		}
	} );
} )( jQuery, document );
