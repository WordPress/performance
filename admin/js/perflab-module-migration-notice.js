/* eslint camelcase: "off", no-alert: "off" */
/* global perflab_module_migration_notice:false */

( function ( document ) {
	document.addEventListener( 'DOMContentLoaded', function () {
		document.addEventListener( 'click', function ( event ) {
			if (
				event.target.classList.contains(
					'perflab-install-active-plugin'
				)
			) {
				const target = event.target;
				target.parentElement
					.querySelector( 'span' )
					.classList.remove( 'hidden' );

				const data = new FormData();
				data.append(
					'action',
					'perflab_install_activate_standalone_plugins'
				);
				data.append( 'nonce', perflab_module_migration_notice.nonce );

				fetch( perflab_module_migration_notice.ajaxurl, {
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
						target.parentElement
							.querySelector( 'span' )
							.classList.add( 'hidden' );
						if ( ! result.success ) {
							alert( result.data.errorMessage );
						}
						window.location.reload();
					} )
					.catch( function ( error ) {
						alert( error.errorMessage );
						window.location.reload();
					} );
			}
		} );
	} );
} )( document );
