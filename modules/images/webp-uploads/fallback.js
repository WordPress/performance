window.wpPerfLab = window.wpPerfLab || {};

( function( document ) {
	window.wpPerfLab.webpUploadsFallbackWebpImages = function( media ) {
		for ( var i = 0; i < media.length; i++ ) {
			try {
				if ( ! media[i].media_details.sources || ! media[i].media_details.sources['image/jpeg'] ) {
					continue;
				}

				var ext = media[i].media_details.sources['image/jpeg'].file.match( /\.\w+$/i );
				if ( ! ext || ! ext[0] ) {
					continue;
				}

				var images = document.querySelectorAll( 'img.wp-image-' + media[i].id );
				for ( var j = 0; j < images.length; j++ ) {
					images[j].src = images[j].src.replace( /\.webp$/i, ext[0] );
					var srcset = images[j].getAttribute( 'srcset' );
					if ( srcset ) {
						images[j].setAttribute( 'srcset', srcset.replace( /\.webp(\s)/ig, ext[0] + '$1' ) );
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
