( function( d ) {
	window.webpUploadsFallbackWebpImages = function( media ) {
		for ( var i = 0; i < media.length; i++ ) {
			try {
				var ext = media[i].media_details.sources['image/jpeg'].file.match( /\.\w+$/i );
				if ( ! ext || ! ext[0] ) {
					continue;
				}

				var images = d.querySelectorAll( 'img.wp-image-' + media[i].id );
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

	var restApi = d.getElementById( 'webpUploadsFallbackWebpImages' ).getAttribute( 'data-rest-api' );

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

			var jsonp = d.createElement( 'script' );
			jsonp.src = restApi + 'wp/v2/media/?_fields=id,media_details&_jsonp=webpUploadsFallbackWebpImages&per_page=100&include=' + pageIds.join( ',' );
			d.body.appendChild( jsonp );
		}
	};

	try {
		// Loop through already available images.
		loadMediaDetails( d.querySelectorAll( 'img' ) );

		// Start the mutation observer to update images added dynamically.
		var observer = new MutationObserver( function( mutationList ) {
			for ( var i = 0; i < mutationList.length; i++ ) {
				loadMediaDetails( mutationList[i].addedNodes );
			}
		} );
	
		observer.observe( d.body, {
			subtree: true,
			childList: true,
		} );
	} catch ( e ) {
	}
} )( document );
