/**
 * External dependencies
 */
const autocannon = require( 'autocannon' );
const { mean, floor } = require( 'lodash' );

/**
 * Internal dependencies
 */
const { log } = require( '../lib/logger' );

/**
 * @typedef BenchmarkCommandOptions
 *
 * @property {string} url         An URL.
 * @property {number} concurrency Number of multiple requests to make at a time.
 * @property {number} requests    Number of requests to perform.
 */
exports.options = [
	{
		argname: '-u, --url <url>',
		description: 'An URL to run benchmark tests for',
	},
	{
		argname: '-c, --concurrency <concurrency>',
		description: 'Number of multiple requests to make at a time',
		defaults: 1,
	},
	{
		argname: '-n, --requests <requests>',
		description: 'Number of requests to perform',
		defaults: 1,
	},
];

/**
 * Runs http benchmarks for an URL.
 *
 * @param {BenchmarkCommandOptions} opt Command options.
 */
exports.handler = async ( opt ) => {
	const { url, concurrency: connections, requests: amount } = opt;
	const metrics = {};
	const responseTimes = [];

	const instance = autocannon( {
		url,
		connections,
		amount,
		setupClient( client ) {
			client.on( 'headers', ( { headers } ) => {
				for ( let i = 0, len = headers.length; i < len; i += 2 ) {
					if ( headers[ i ].toLowerCase() !== 'server-timing' ) {
						continue;
					}

					headers[ i + 1 ]
						.split( ',' )
						.map( ( timing ) => timing.trim().split( ';dur=' ) )
						.forEach( ( [ key, value ] ) => {
							metrics[ key ] = metrics[ key ] || [];
							metrics[ key ].push( +value );
						} );
				}
			} );

			client.on( 'response', ( statusCode, resBytes, responseTime ) => {
				responseTimes.push( responseTime );
			} );
		},
	} );

	process.once( 'SIGINT', () => {
		instance.stop();
	} );

	instance.on( 'done', () => {
		log( `Avr response time: ${ floor( mean( responseTimes ), 2 ) }ms` );

		for ( const metric of Object.keys( metrics ) ) {
			const metricAvgMs = floor( mean( metrics[ metric ] ), 2 );
			log( ` - ${ metric }: ${ metricAvgMs }ms` );
		}
	} );
};
