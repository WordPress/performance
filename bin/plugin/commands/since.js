/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );
const glob = require( 'fast-glob' );
const { log, formats } = require( '../lib/logger' );

/**
 * Internal dependencies
 */
const { plugins } = require( '../../../plugins.json' );

/**
 * @typedef WPSinceCommandOptions
 *
 * @property {string=} plugin Plugin slug.
 */

exports.options = [
	{
		argname: '-p, --plugin <plugin>',
		description: 'Plugin slug. Defaults to update all.',
	},
];

/**
 * Replaces "@since n.e.x.t" tags in the code with the current release version.
 *
 * @param {WPSinceCommandOptions} opt Command options.
 */
exports.handler = async ( opt ) => {
	const isAllPlugins = ! opt.plugin;

	if ( ! isAllPlugins && ! plugins.includes( opt.plugin ) ) {
		throw new Error(
			`The plugin "${ opt.plugin }" is not a valid plugin managed as part of this project.`
		);
	}

	const pluginDirectories = [];
	const pluginRoot = path.resolve( __dirname, '../../../' );

	if ( isAllPlugins ) {
		for ( const pluginSlug of plugins ) {
			pluginDirectories.push(
				path.resolve( pluginRoot, 'plugins', pluginSlug )
			);
		}
	} else {
		pluginDirectories.push(
			path.resolve( pluginRoot, 'plugins', opt.plugin )
		);
	}

	for ( const pluginDirectory of pluginDirectories ) {
		const patterns = [];
		const ignore = [ '**/node_modules', '**/vendor', '**/bin', '**/build' ];
		const pluginSlug = path.basename( pluginDirectory );

		const readmeFile = path.resolve( pluginDirectory, 'readme.txt' );
		const readmeContent = fs.readFileSync( readmeFile, 'utf-8' );
		const readmeContentMatches = readmeContent.match(
			/^Stable tag:\s+(\d+\.\d+\.\d+)$/m
		);
		if ( ! readmeContentMatches ) {
			throw new Error(
				`Unable to parse out "Stable tag" from ${ readmeFile }.`
			);
		}
		const version = readmeContentMatches[ 1 ];

		patterns.push( `${ pluginDirectory }/**/*.php` );
		patterns.push( `${ pluginDirectory }/**/*.js` );

		const files = await glob( patterns, {
			ignore,
		} );

		const regexps = [
			/(@since\s+)n\.e\.x\.t/g,
			/('[^']*?)n\.e\.x\.t(?=')/g,
		];

		let replacementCount = 0;
		for ( const file of files ) {
			for ( const regexp of regexps ) {
				const content = fs.readFileSync( file, 'utf-8' );
				if ( regexp.test( content ) ) {
					fs.writeFileSync(
						file,
						content.replace(
							regexp,
							function ( matches, sinceTag ) {
								replacementCount++;
								return sinceTag + version;
							}
						)
					);
				}
			}
		}

		const commonMessage = `Using version ${ version } for ${ pluginSlug }: `;
		if ( replacementCount > 0 ) {
			log(
				formats.success(
					commonMessage +
						( replacementCount === 1
							? '1 replacement'
							: `${ replacementCount } replacements` )
				)
			);
		} else {
			log( commonMessage + 'No replacements' );
		}
	}
};
