/**
 * @typedef BenchmarkCommandOptions
 *
 * @property {string} url An URL.
 */
exports.options = [
	{
		argname: '-u, --url <url>',
		description: 'An URL to run benchmark tests for',
	},
];

/**
 * Runs http benchmarks for an URL.
 *
 * @param {BenchmarkCommandOptions} opt Command options.
 */
exports.handler = async ( opt ) => {
};
