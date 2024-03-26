/* eslint camelcase: "off", no-alert: "off" */
/* global perflab_install_activate_plugins:false */

( function ( document ) {
	document.addEventListener( 'DOMContentLoaded', function () {
		document.addEventListener( 'click', function ( event ) {
			if (
				event.target.classList.contains(
					'perflab-install-active-plugin'
				)
			) {
				const target = event.target;
				target.classList.add( 'updating-message' );
				target.innerHTML = wp.i18n.__( 'Activating…' );

				const data = new FormData();
				data.append( 'action', 'perflab_install_activate_plugins' );
				data.append( 'nonce', perflab_install_activate_plugins.nonce );
				data.append( 'slug', target.dataset.slug );

				fetch( perflab_install_activate_plugins.ajaxurl, {
					method: 'POST',
					credentials: 'same-origin',
					body: data,
				} )
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error(
								wp.i18n.__(
									'Network response was not ok.',
									'performance-lab'
								)
							);
						}
						return response.json();
					} )
					.then( function ( result ) {
						target.classList.remove( 'updating-message' );
						if ( ! result.success ) {
							target.innerHTML = wp.i18n.__( 'Activate' );
							alert( result.data.errorMessage );
						} else {
							target.innerHTML = wp.i18n.__( 'Active' );
							target.disabled = true;
						}
					} )
					.catch( function ( error ) {
						alert( error.errorMessage );
					} );
			}
		} );
	} );
} )( document );
