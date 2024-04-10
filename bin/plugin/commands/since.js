/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const glob = require( 'fast-glob' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );
const { plugins } = require( '../../../plugins.json' );

/**
 * @typedef WPSinceCommandOptions
 *
 * @property {string} plugin  Plugin slug.
 * @property {string} release Release version number.
 */

exports.options = [
	{
		argname: '-p, --plugin <plugin>',
		description: 'Plugin slug',
		defaults: 'performance-lab',
	},
	{
		argname: '-r, --release <release>',
		description: 'Release version number',
	},
];

/**
 * Replaces "@since n.e.x.t" tags in the code with the current release version.
 *
 * @param {WPSinceCommandOptions} opt Command options.
 */
exports.handler = async ( opt ) => {
	if ( ! opt.release ) {
		log(
			formats.error(
				'The release version must be provided via the --release (-r) argument.'
			)
		);

		process.exit( 1 );
	}

	if (
		opt.plugin !== 'performance-lab' &&
		! plugins.includes( opt.plugin )
	) {
		log(
			formats.error(
				`The plugin "${ opt.plugin }" is not a valid plugin managed as part of this project.`
			)
		);

		process.exit( 1 );
	}

	const patterns = [];
	const pluginRoot = path.resolve( __dirname, '../../../' );
	const ignore = [ '**/node_modules', '**/vendor', '**/bin', '**/build' ];

	/*
	 * For a standalone plugin, use the specific plugin directory.
	 * For Performance Lab, use the root directory and ignore the standalone plugin directories.
	 */
	if ( opt.plugin !== 'performance-lab' ) {
		const pluginPath = path.resolve( pluginRoot, 'plugins', opt.plugin );

		patterns.push( `${ pluginPath }/**/*.php` );
		patterns.push( `${ pluginPath }/**/*.js` );
	} else {
		ignore.push( '**/plugins' );
		patterns.push( `${ pluginRoot }/**/*.php` );
		patterns.push( `${ pluginRoot }/**/*.js` );
	}

	const files = await glob( patterns, {
		ignore,
	} );

	const regexp = new RegExp( '@since(\\s+)n.e.x.t', 'g' );

	files.forEach( ( file ) => {
		const content = fs.readFileSync( file, 'utf-8' );
		if ( regexp.test( content ) ) {
			fs.writeFileSync(
				file,
				content.replace( regexp, `@since$1${ opt.release }` )
			);
		}
	} );
};
