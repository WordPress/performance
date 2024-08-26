( async () => {
	// Start the AI engine.
	// eslint-disable-next-line no-console -- For testing only.
	console.log( 'Starting...' );

	async function predictLinks( { close } ) {
		close();

		const session = await window.ai.assistant.create();

		const stream = session.promptStreaming(
			`You will predict the links on a web page that visitors are most likely to visit. We will pre-render those links to make them load instantly for users. Please provide the five links users are most likely to visit as a JSON object. Here is the HTML: ${ document.documentElement.outerHTML }`
		);

		let result = '';

		for await ( const value of stream ) {
			result = value;
		}

		// eslint-disable-next-line no-console -- For testing only.
		console.log( result );

		// Add a prerender link for each URL using the Speculation Rules API
		const links = JSON.parse( result );

		for ( const link of links ) {
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
			prerenderLink.textContent = rules.replace( 'URL', link );
			document.head.appendChild( prerenderLink );
		}

		close();
	}
	await predictLinks();
} )();
