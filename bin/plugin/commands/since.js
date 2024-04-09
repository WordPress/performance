/**
 * External dependencies
 */
const path = require( 'path' );
const glob = require( 'fast-glob' );
const fs = require( 'fs' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );
const { plugins } = require( '../../../plugins.json' );

/**
 * @typedef WPSinceCommandOptions
 *
 * @property {string} version Version number.
 */

exports.options = [
	{
		argname: '-p, --plugin <plugin>',
		description: 'Plugin name',
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
		return;
	}

	if ( opt.plugin && ! plugins.includes( opt.plugin ) ) {
		log(
			formats.error(
				`The plugin "${ opt.plugin }" is not found in the plugins.json file.`
			)
		);
		return;
	}

	const patterns = [];
	const pluginRoot = path.resolve( __dirname, '../../../' );
	const ignore = [ '**/node_modules', '**/vendor', '**/bin', '**/build' ];

	if ( opt.plugin ) {
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
