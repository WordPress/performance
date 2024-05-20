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
 * @typedef WPVersionsCommandOptions
 *
 * @property {?string} plugin Plugin slug.
 */

exports.options = [
	{
		argname: '-p, --plugin <plugin>',
		description: 'Plugin slug',
		defaults: null,
	},
];

/**
 * Checks a plugin directory for the required versions.
 *
 * @throws Error When there are inconsistent versions.
 *
 * @param {string} pluginDirectory
 * @return {Promise<string>} Consistent version.
 */
async function checkPluginDirectory( pluginDirectory ) {
	const readmeFilePath = path.resolve( pluginDirectory, 'readme.txt' );
	const readmeContents = fs.readFileSync( readmeFilePath, 'utf-8' );

	const stableTagVersionMatches = readmeContents.match(
		/^Stable tag:\s*(\d+\.\d+\.\d+(?:-\w+)?)$/m
	);
	if ( ! stableTagVersionMatches ) {
		throw new Error( `Unable to locate stable tag in ${ readmeFilePath }` );
	}
	const stableTagVersion = stableTagVersionMatches[ 1 ];

	const latestChangelogMatches = readmeContents.match(
		/^== Changelog ==\n+= (\d+\.\d+\.\d+(?:-\w+)?) =$/m
	);
	if ( ! latestChangelogMatches ) {
		throw new Error(
			'Unable to latest version entry in readme changelog.'
		);
	}
	const latestChangelogVersion = latestChangelogMatches[ 1 ];

	// Find the bootstrap file.
	let phpBootstrapFileContents = null;
	for ( const phpFile of await glob(
		path.resolve( pluginDirectory, '*.php' )
	) ) {
		const phpFileContents = fs.readFileSync( phpFile, 'utf-8' );
		if ( /^<\?php\n\/\*\*\n \* Plugin Name:/.test( phpFileContents ) ) {
			phpBootstrapFileContents = phpFileContents;
			break;
		}
	}
	if ( ! phpBootstrapFileContents ) {
		throw new Error( 'Unable to locate the PHP bootstrap file.' );
	}

	const headerVersionMatches = phpBootstrapFileContents.match(
		/^ \* Version:\s+(\d+\.\d+\.\d+(?:-\w+)?)$/m
	);
	if ( ! headerVersionMatches ) {
		throw new Error(
			'Unable to locate version in plugin PHP bootstrap file header.'
		);
	}
	const headerVersion = headerVersionMatches[ 1 ];

	const phpLiteralVersionMatches = phpBootstrapFileContents.match(
		/'(\d+\.\d+\.\d+(?:-\w+)?)'/
	);
	if ( ! phpLiteralVersionMatches ) {
		throw new Error( 'Unable to locate the PHP literal version.' );
	}
	const phpLiteralVersion = phpLiteralVersionMatches[ 1 ];

	const allVersions = [
		stableTagVersion,
		latestChangelogVersion,
		headerVersion,
		phpLiteralVersion,
	];

	if ( ! allVersions.every( ( version ) => version === stableTagVersion ) ) {
		throw new Error(
			`Version mismatch: ${ JSON.stringify(
				{
					latestChangelogVersion,
					headerVersion,
					stableTagVersion,
					phpLiteralVersion,
				},
				null,
				4
			) }`
		);
	}

	return stableTagVersion;
}

/**
 * Checks that the versions are consistent across all plugins, including in:
 *
 * - Plugin bootstrap header metadata comment.
 * - Plugin bootstrap PHP literal (e.g. constant).
 * - The 'Stable tag' in readme.txt.
 * - The most recent changelog entry in readme.txt.
 *
 * @param {WPVersionsCommandOptions} opt Command options.
 */
exports.handler = async ( opt ) => {
	const pluginRoot = path.resolve( __dirname, '../../../' );

	const pluginDirectories = [];

	if ( ! opt.plugin || 'performance-lab' === opt.plugin ) {
		pluginDirectories.push( pluginRoot ); // TODO: Remove this after <https://github.com/WordPress/performance/pull/1182>.
	}

	for ( const pluginSlug of plugins ) {
		if ( ! opt.plugin || pluginSlug === opt.plugin ) {
			pluginDirectories.push(
				path.resolve( pluginRoot, 'plugins', pluginSlug )
			);
		}
	}

	let errorCount = 0;
	for ( const pluginDirectory of pluginDirectories ) {
		const slug = path.basename( pluginDirectory );
		try {
			const version = await checkPluginDirectory( pluginDirectory );
			if ( version.includes( '-' ) ) {
				log(
					formats.warning(
						`⚠ ${ slug }: ${ version } (pre-release identifier is present)`
					)
				);
			} else {
				log( formats.success( `✅ ${ slug }: ${ version } ` ) );
			}
		} catch ( error ) {
			errorCount++;
			log( formats.error( `❌ ${ slug }: ${ error.message }` ) );
		}
	}

	if ( errorCount > 0 ) {
		throw new Error(
			`There are ${ errorCount } plugin(s) with inconsistent versions.`
		);
	}
};
