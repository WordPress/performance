jQuery( document ).ready( function() {
	console.log( wpp_plugin_manager );

	var gateRequest = false;

	jQuery('[data-wpp-plugin]').on( 'click', function( e ) {
		if ( gateRequest ) {
			return;
		}

		// Build vars from event targets.
		var currentTarget   = jQuery(e.target)[0];
		var wppPluginSlug   = jQuery(e.currentTarget).data('wpp-plugin');
		var wppPluginAction = jQuery(e.target).text().trim().toLowerCase();

		// Only trigger actions if the plugin card button is clicked.
		if (
			'BUTTON' !== currentTarget.tagName &&
			'button' !== currentTarget.className
		) {
			gateRequest = false;
			return;
		}

		// Temporarily disable button.
		currentTarget.disabled = true;

		// If plugin action is invalid, halt.
		if (
			'install' !== wppPluginAction &&
			'activate' !== wppPluginAction &&
			'deactivate' !== wppPluginAction &&
			'uninstall' !== wppPluginAction
		) {
			gateRequest = false;
			return;
		}

		// If the plugin slug is not identified by WPP, halt.
		if ( ! wpp_plugin_manager.wpp_plugins.hasOwnProperty( wppPluginSlug ) ) {
			gateRequest = false;
			return;
		}

		jQuery.ajax( {
			url: wpp_plugin_manager.rest_base + wpp_plugin_manager.rest_namespace + '/plugins/' + wppPluginAction,
			method: 'GET',
			beforeSend: function ( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', wpp_plugin_manager.nonce );
			},
			data:{
				plugin: wppPluginSlug
			}
		} ).done( function ( resp ) {
			console.log( resp );
			console.log( resp.response );
			window.location.reload();
		} ).error( function ( err ) {
			gateRequest = false;
		} );

	} );

} );
