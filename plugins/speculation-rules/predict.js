( async () => {
	// Start the AI engine.
	// eslint-disable-next-line no-console -- For testing only.
	console.log( 'Starting...' );

	async function predictLinks() {
		const session = await window.ai.assistant.create();

		const body = document.getElementsByTagName( 'body' )[ 0 ].innerHTML;

		//const prompt = `Predict the five links users are most likely to visit on this page, returning ONLY the 5 full urls as a JSON object: ${ body }`;
		// const prompt = `Given a web page's HTML, find the links and predict which is most likely to be clicked by a user. Return the top 5 links as a JSON object. Only return the JSON object as your complete answer. Here is the HTML: ${ body }`;
		const prompt = `In order to prerender links so users get instant navigations, can you predict the three links users are most likely to visit on this page, returning ONLY the 3 full urls as a JSON object in the format ['url','url2','url3']. Here is the HTML: ${ body }`;

		// eslint-disable-next-line no-console -- For testing only.
		console.log( `The size of the prompt is ${ prompt.length }.` );

		let result = false;
		try {
			result = await session.prompt( prompt );
		} catch ( error ) {
			// eslint-disable-next-line no-console -- For testing only.
			console.error( error );
			return;
		}

		if ( ! result ) {
			// eslint-disable-next-line no-console -- For testing only.
			console.log( 'No result.' );
			return;
		}

		// Log result so far
		// eslint-disable-next-line no-console -- For testing only.
		console.log( result );

		// Grab everything after "Output:" or "```json" strings etc...
		const output =
			result.split( 'Output:' )[ 1 ] ||
			result.split( '```json' )[ 1 ] ||
			result.split( 'Answer:' )[ 1 ] ||
			result.split( 'Solution:' )[ 1 ];

		// If there is no output, return.
		if ( ! output ) {
			// eslint-disable-next-line no-console -- For testing only.
			console.log( 'No output.' );
			return;
		}

		// Remove  the two "```" formatting strings.
		result = output.replace( /```JSON/g, '' );
		result = output.replace( /```/g, '' );

		// Remove any newlines.
		result = result.replace( /\n/g, '' );

		// Remove any whitespace.
		result = result.replace( / /g, '' );

		// eslint-disable-next-line no-console -- For testing only.
		console.log( result );

		// Add a prerender link for each URL using the Speculation Rules API
		const resultData = JSON.parse( result );

		for ( const link of resultData.links ) {
			// eslint-disable-next-line no-console -- For testing only.
			console.log( `Link: ${ link }` );
			/**
			 * Add speculation rules API prerendering.
			 *
			 * It will be something like this:
			 *
			 * <script type="speculationrules">
			 * 		{
			 * 			"prerender": [
			 * 			{
			 * 				"where": { "href_matches": "URL" },
			 * 				"eagerness": "moderate"
			 * 			}]
			 * 		}
			 * 	</script>
			 */
			const prerenderLink = document.createElement( 'link' );
			prerenderLink.type = 'speculationrules';
			const rules =
				'{ "prerender": [ { "where": { "href_matches": "URL" }, "eagerness": "moderate" }] }';
			prerenderLink.textContent = rules.replace( 'URL', link.href );
			document.head.appendChild( prerenderLink );

			// eslint-disable-next-line no-console -- For testing only.
			console.log( `Prerendering link: ${ link }` );
		}
	}
	await predictLinks();
} )();
