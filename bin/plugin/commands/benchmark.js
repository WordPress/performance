/**
 * External dependencies
 */
const fs = require( 'fs' );
const readline = require( 'readline' );
const autocannon = require( 'autocannon' );
const { mean, floor } = require( 'lodash' );
const { table } = require( 'table' );

/**
 * Internal dependencies
 */
const { log } = require( '../lib/logger' );

/**
 * @typedef BenchmarkOptions
 *
 * @property {string} url         An URL.
 * @property {number} connections Number of multiple requests to make at a time.
 * @property {number} amount      Number of requests to perform.
 */

/**
 * @typedef BenchmarkResults
 *
 * @property {Array.<number>} responseTimes An array of response times.
 * @property {Object}         metrics       The Server-Timing metrics object.
 */

/**
 * @typedef BenchmarkCommandOptions
 *
 * @property {string} url         An URL to benchmark.
 * @property {number} concurrency Number of multiple requests to make at a time.
 * @property {number} requests    Number of requests to perform.
 * @property {string} file        A path to a file with URLs to benchmark.
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
	{
		argname: '-f, --file <file>',
		description: 'File with URLs to run benchmark tests for',
	},
];

/**
 * Runs http benchmarks for an URL.
 *
 * @param {BenchmarkCommandOptions} opt Command options.
 */
exports.handler = async ( opt ) => {
	const { concurrency: connections, requests: amount } = opt;
	const results = [];

	for await ( const url of getURLs( opt ) ) {
		const { responseTimes, metrics } = await benchmarkURL( {
			url,
			connections,
			amount,
		} );

		results.push( [ url, responseTimes, metrics ] );
	}

	outputResults( opt, results );
};

/**
 * Generates URLs to benchmark based on command arguments. If both "<url>" and "<file>" arguments
 * are passed to the command, then both will be used to generate URLs.
 *
 * @param {BenchmarkCommandOptions} opt Command options.
 */
async function* getURLs( opt ) {
	if ( !! opt.url ) {
		yield opt.url;
	}

	if ( !! opt.file ) {
		const rl = readline.createInterface( {
			input: fs.createReadStream( opt.file ),
			crlfDelay: Infinity,
		} );

		for await ( const url of rl ) {
			if ( url.length > 0 ) {
				yield url;
			}
		}
	}
}

/**
 * Benchmarks an URL and returns response time and server-timing metrics for every request.
 *
 * @param {BenchmarkOptions} params Benchmark parameters.
 * @return {BenchmarkResults} Response times and metrics arrays.
 */
function benchmarkURL( params ) {
	const metrics = {};
	const responseTimes = [];

	const onHeaders = ( { headers } ) => {
		const responseMetrics = getServerTimingMetricsFromHeaders( headers );
		Object.entries( responseMetrics ).forEach( ( [ key, value ] ) => {
			metrics[ key ] = metrics[ key ] || [];
			metrics[ key ].push( +value );
		} );
	};

	const onResponse = ( statusCode, resBytes, responseTime ) => {
		responseTimes.push( responseTime );
	};

	const instance = autocannon( {
		...params,
		setupClient( client ) {
			client.on( 'headers', onHeaders );
			client.on( 'response', onResponse );
		},
	} );

	const onStop = instance.stop.bind( instance );
	process.once( 'SIGINT', onStop );

	return new Promise( ( resolve ) => {
		instance.on( 'done', () => {
			process.off( 'SIGINT', onStop );
			resolve( { responseTimes, metrics } );
		} );
	} );
}

/**
 * Reads the Server-Timing metrics from the response headers.
 *
 * @param {Array.<string>} headers Array of response headers information where each even element is a header name and an odd element is the header value.
 * @return {Object} An object where keys are metric names and values are metric values.
 */
function getServerTimingMetricsFromHeaders( headers ) {
	for ( let i = 0, len = headers.length; i < len; i += 2 ) {
		if ( headers[ i ].toLowerCase() !== 'server-timing' ) {
			continue;
		}

		return headers[ i + 1 ]
			.split( ',' )
			.map( ( timing ) => timing.trim().split( ';dur=' ) )
			.reduce(
				( obj, [ key, value ] ) => ( { ...obj, [ key ]: value } ),
				{}
			);
	}

	return {};
}

/**
 * Ouptuts results of benchmarking.
 *
 * @param {BenchmarkCommandOptions} opt     Command options.
 * @param {Array.<Array>}           results A collection of benchmark results for each URL.
 */
function outputResults( opt, results ) {
	const len = results.length;
	const allMetricNames = {};

	const newRow = ( title ) => {
		const line = new Array( len + 1 ).fill( '' );
		line[ 0 ] = title;
		return line;
	};

	const tableData = [ newRow( '' ), newRow( 'Avg Time' ) ];

	for ( let i = 0; i < len; i++ ) {
		for ( const metric of Object.keys( results[ i ][ 2 ] ) ) {
			allMetricNames[ metric ] = '';
		}
	}

	Object.keys( allMetricNames ).forEach( ( name ) => {
		tableData.push( newRow( name ) );
	} );

	for ( let i = 0; i < len; i++ ) {
		const [ url, responseTimes, metrics ] = results[ i ];

		tableData[ 0 ][ i + 1 ] = url;
		tableData[ 1 ][ i + 1 ] = floor( mean( responseTimes ), 2 );

		for ( const metric of Object.keys( metrics ) ) {
			const metricAvgMs = floor( mean( metrics[ metric ] ), 2 );

			for ( let j = 2; j < tableData.length; j++ ) {
				if ( tableData[ j ][ 0 ] === metric ) {
					tableData[ j ][ i + 1 ] = metricAvgMs;
				}
			}
		}
	}

	log( table( tableData ) );
}
