( function( document ) {
	document.addEventListener( 'DOMContentLoaded', function() {
		var standalone_plugin = [];
		var pluginCards       = document.querySelectorAll( '.wpp-standalone-plugins .plugin-card' );

		pluginCards.forEach( function( card ) {
			if ( card.querySelector( '.notice-warning') ) {
				standalone_plugin.push( card.getAttribute( 'data-module' ) );
			}
		});

		document.addEventListener( 'click', function(event) {
			if ( event.target.classList.contains( 'perflab-install-active-plugin' ) ) {
                if ( ! perflab_admin.has_permission ) {
                    alert( perflab_admin.permission_error );
                    return;
                }
				if ( confirm( perflab_admin.prompt_message ) ) {
					var __this = event.target;
					__this.parentElement.querySelector( 'span' ).classList.remove( 'hidden' );

					var data = new FormData();
					data.append( 'action', 'perflab_install_activate_standalone_plugins' );
					data.append( 'nonce', perflab_admin.nonce );
					data.append( 'data', standalone_plugin );
	
					fetch( perflab_admin.ajaxurl, {
						method: 'POST',
						credentials: 'same-origin',
						body: data
					})
					.then( function ( response ) {
						if ( ! response.ok ) {
							throw new Error( perflab_admin.network_error );
						}
						return response.json();
					})
					.then( function( result ) {
						__this.parentElement.querySelector( 'span' ).classList.add( 'hidden' );
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
