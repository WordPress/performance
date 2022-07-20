window.wpPerfLab = window.wpPerfLab || {};

( function( document ) {
	window.wpPerfLab.webpUploadsFallbackWebpImages = function( media ) {
		for ( var i = 0; i < media.length; i++ ) {
			try {
				var webp_sizes = window.wpPerfLabWebpSizes;
				var size_keys = Object.keys( webp_sizes );

				var images = document.querySelectorAll( 'img.wp-image-' + media[i].id );
				for ( var j = 0; j < images.length; j++ ) {
					console.log( media[i] );
					var srcset = images[j].getAttribute( 'srcset' );
					if ( srcset ) {
						// Update full image in srcset.
						srcset = srcset.replace( images[j].src, media[i].media_details.sizes.full.source_url );
						images[j].setAttribute( 'srcset', srcset );

						// Update full image in src.
						images[j].src = media[i].media_details.sizes.full.source_url;

						// Update thumbnail images.
						for ( var k = 0; k < size_keys.length; k++ ) {

							if( ! media[i].media_details.sizes[size_keys[k]].sources['image/webp'] ) {
								continue;
							}

							srcset = srcset.replace( media[i].media_details.sizes[size_keys[k]].sources['image/webp'].source_url, media[i].media_details.sizes[size_keys[k]].sources['image/jpeg'].source_url );
							images[j].setAttribute( 'srcset', srcset );
						}

					}
				}
			} catch ( e ) {
			}
		}
	};

	var restApi = document.getElementById( 'webpUploadsFallbackWebpImages' ).getAttribute( 'data-rest-api' );

	var loadMediaDetails = function( nodes ) {
		var ids = [];
		for ( var i = 0; i < nodes.length; i++ ) {
			var node = nodes[i];
			var srcset = node.getAttribute( 'srcset' ) || '';

			if (
				node.nodeName !== "IMG" ||
				( ! node.src.match( /\.webp$/i ) && ! srcset.match( /\.webp\s+/ ) )
			) {
				continue;
			}

			var attachment = node.className.match( /wp-image-(\d+)/i );
			if ( attachment && attachment[1] && ids.indexOf( attachment[1] ) === -1 ) {
				ids.push( attachment[1] );
			}
		}

		for ( var page = 0, pages = Math.ceil( ids.length / 100 ); page < pages; page++ ) {
			var pageIds = [];
			for ( var i = 0; i < 100 && i + page * 100 < ids.length; i++ ) {
				pageIds.push( ids[ i + page * 100 ] );
			}

			var jsonp = document.createElement( 'script' );
			jsonp.src = restApi + 'wp/v2/media/?_fields=id,media_details&_jsonp=wpPerfLab.webpUploadsFallbackWebpImages&per_page=100&include=' + pageIds.join( ',' );
			document.body.appendChild( jsonp );
		}
	};

	try {
		// Loop through already available images.
		loadMediaDetails( document.querySelectorAll( 'img' ) );

		// Start the mutation observer to update images added dynamically.
		var observer = new MutationObserver( function( mutationList ) {
			for ( var i = 0; i < mutationList.length; i++ ) {
				loadMediaDetails( mutationList[i].addedNodes );
			}
		} );
	
		observer.observe( document.body, {
			subtree: true,
			childList: true,
		} );
	} catch ( e ) {
	}
} )( document );
