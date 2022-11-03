/**
 * External dependencies
 */
const fs = require( 'fs' );
const readline = require( 'readline' );
const autocannon = require( 'autocannon' );
const { round } = require( 'lodash' );
const { table } = require( 'table' );
const { stringify: csv } = require( 'csv-stringify/sync' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );

const OUTPUT_FORMAT_CSV = 'csv';

/**
 * @typedef BenchmarkOptions
 *
 * @property {string} url         An URL.
 * @property {number} connections A number of multiple requests to make at a time.
 * @property {number} amount      A number of requests to perform.
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
 * @property {number} concurrency A number of multiple requests to make at a time.
 * @property {number} number      A number of requests to perform.
 * @property {string} file        A path to a file with URLs to benchmark.
 * @property {string} output      An output format.
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
		argname: '-n, --number <number>',
		description: 'Number of requests to perform',
		defaults: 1,
	},
	{
		argname: '-f, --file <file>',
		description: 'File with URLs to run benchmark tests for',
	},
	{
		argname: '-o, --output <output>',
		description: 'Output format: csv or table',
		defaults: 'table',
	},
];

/**
 * Runs http benchmarks for an URL.
 *
 * @param {BenchmarkCommandOptions} opt Command options.
 */
exports.handler = async ( opt ) => {
	const { concurrency: connections, number: amount } = opt;
	const results = [];

	for await ( const url of getURLs( opt ) ) {
		const { completeRequests, responseTimes, metrics } = await benchmarkURL(
			{
				url,
				connections,
				amount,
			}
		);

		results.push( [ url, completeRequests, responseTimes, metrics ] );
	}

	if ( results.length === 0 ) {
		log(
			formats.error(
				'You need to provide a URL to benchmark via the --url (-u) argument, or a file with multiple URLs via the --file (-f) argument.'
			)
		);
	} else {
		outputResults( opt, results );
	}
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
	let completeRequests = 0;

	const onHeaders = ( { headers } ) => {
		const responseMetrics = getServerTimingMetricsFromHeaders( headers );
		Object.entries( responseMetrics ).forEach( ( [ key, value ] ) => {
			metrics[ key ] = metrics[ key ] || [];
			metrics[ key ].push( +value );
		} );
	};

	const onResponse = ( statusCode, resBytes, responseTime ) => {
		if ( statusCode === 200 ) {
			completeRequests++;
		}

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
			resolve( { responseTimes, completeRequests, metrics } );
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

	const tableData = [
		newRow( '' ),
		newRow( 'Success Rate' ),
		newRow( 'Response Time' ),
	];

	for ( let i = 0; i < len; i++ ) {
		for ( const metric of Object.keys( results[ i ][ 3 ] ) ) {
			allMetricNames[ metric ] = '';
		}
	}

	Object.keys( allMetricNames ).forEach( ( name ) => {
		tableData.push( newRow( name ) );
	} );

	for ( let i = 0; i < len; i++ ) {
		const [ url, completeRequests, responseTimes, metrics ] = results[ i ];
		const completionRate = round(
			( 100 * completeRequests ) / ( opt.number || 1 ),
			1
		);

		tableData[ 0 ][ i + 1 ] = url;
		tableData[ 1 ][ i + 1 ] = `${ completionRate }%`;
		tableData[ 2 ][ i + 1 ] = calcMedian( responseTimes );

		for ( const metric of Object.keys( metrics ) ) {
			const metricAvgMs = calcMedian( metrics[ metric ] );

			for ( let j = 3; j < tableData.length; j++ ) {
				if ( tableData[ j ][ 0 ] === metric ) {
					tableData[ j ][ i + 1 ] = metricAvgMs;
				}
			}
		}
	}

	const output =
		OUTPUT_FORMAT_CSV === opt.output.toLowerCase()
			? csv( tableData )
			: table( tableData );

	log( output );
}

/**
 * Calculates and returns a median value for a set of values.
 *
 * @param {Array.<number>} values An array of values.
 * @return {number} A median value.
 */
function calcMedian( values ) {
	const len = values.length;
	if ( len === 0 ) {
		return 0;
	}

	const list = [ ...values ];
	list.sort( ( a, b ) => b - a );

	const median =
		len % 2 === 0
			? ( list[ len / 2 ] + list[ len / 2 - 1 ] ) / 2
			: list[ Math.floor( len / 2 ) ];

	return round( median, 2 );
}
