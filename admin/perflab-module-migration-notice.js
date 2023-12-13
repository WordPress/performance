( function( document ) {
	document.addEventListener( 'DOMContentLoaded', function() {
		document.addEventListener( 'click', function(event) {
			if ( event.target.classList.contains( 'perflab-install-active-plugin' ) ) {
                if ( ! perflab_module_migration_notice.has_permission ) {
                    alert( perflab_module_migration_notice.permission_error );
                    return;
                }

				if ( confirm( perflab_module_migration_notice.prompt_message ) ) {
					var target = event.target;
					target.parentElement.querySelector( 'span' ).classList.remove( 'hidden' );

					var data = new FormData();
					data.append( 'action', 'perflab_install_activate_standalone_plugins' );
					data.append( 'nonce', perflab_module_migration_notice.nonce );
	
					fetch( perflab_module_migration_notice.ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						body: data
					})
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( perflab_module_migration_notice.network_error );
						}
						return response.json();
					})
					.then( function( result ) {
						target.parentElement.querySelector( 'span' ).classList.add( 'hidden' );
						if ( result.error ) {
							alert( result.data.errorMessage );
						}
						window.location.reload();
					})
					.catch( function( error ) {
						alert( error.errorMessage );
						window.location.reload();
					});
				}
			}
		});
	});
} )( document );
