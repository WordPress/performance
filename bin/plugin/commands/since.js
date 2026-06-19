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
const { getReleaseMilestones } = require( './bump-versions' );

/**
 * @typedef WPSinceCommandOptions
 *
 * @property {string=}  plugin Plugin slug.
 * @property {string=}  token  Optional personal GitHub access token.
 * @property {boolean=} all    Update every plugin, ignoring release milestones (legacy behavior).
 */

exports.options = [
	{
		argname: '-p, --plugin <plugin>',
		description:
			'Plugin slug. Defaults to all plugins with an open, dated release milestone.',
	},
	{
		argname: '-t, --token <token>',
		description: 'GitHub token',
	},
	{
		argname: '-a, --all',
		description:
			'Update all plugins, ignoring release milestones (legacy behavior). When combined with --plugin, that plugin is updated without checking for a milestone.',
		defaults: false,
	},
];

/**
 * Replaces "@since n.e.x.t" tags in the code with the current release version.
 *
 * @param {WPSinceCommandOptions} opt Command options.
 */
exports.handler = async ( opt ) => {
	if ( opt.plugin && ! plugins.includes( opt.plugin ) ) {
		throw new Error(
			`The plugin "${ opt.plugin }" is not a valid plugin managed as part of this project.`
		);
	}

	let targetSlugs;
	if ( opt.all ) {
		// Legacy behavior: update every plugin (or the given one), ignoring milestones.
		targetSlugs = opt.plugin ? [ opt.plugin ] : plugins;
	} else {
		// Default: only update plugins that are being released, i.e. those with an open
		// milestone that has a due date and whose title does not contain "n.e.x.t".
		const releaseSlugs = ( await getReleaseMilestones( opt.token ) ).map(
			( milestone ) => milestone.slug
		);
		targetSlugs = opt.plugin
			? releaseSlugs.filter( ( slug ) => slug === opt.plugin )
			: releaseSlugs;

		if ( targetSlugs.length === 0 ) {
			log(
				formats.warning(
					opt.plugin
						? `⚠ Skipping ${ opt.plugin }: no open release milestone with a due date (and without "n.e.x.t") was found. Pass --all to update it anyway.`
						: '⚠ No plugins have an open release milestone with a due date (and without "n.e.x.t"); nothing to update. Pass --all to update every plugin.'
				)
			);
			return;
		}
	}

	const pluginRoot = path.resolve( __dirname, '../../../' );
	const pluginDirectories = targetSlugs.map( ( pluginSlug ) =>
		path.resolve( pluginRoot, 'plugins', pluginSlug )
	);

	for ( const pluginDirectory of pluginDirectories ) {
		const patterns = [];
		const ignore = [ '**/node_modules', '**/vendor', '**/bin', '**/build' ];
		const pluginSlug = path.basename( pluginDirectory );

		const readmeFile = path.resolve( pluginDirectory, 'readme.txt' );
		const readmeContent = fs.readFileSync( readmeFile, 'utf-8' );
		const readmeContentMatches = readmeContent.match(
			/^Stable tag:\s+(\d+\.\d+\.\d+(?:-[\w\.]+)?)$/m
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

		// Update versions in Changelog and Upgrade Notices.
		const readmeContentUpdated = readmeContent.replace(
			/(^= )(n\.e\.x\.t)( =$)/gm,
			function ( matches, before, next, after ) {
				replacementCount++;
				return before + version + after;
			}
		);
		if ( readmeContent !== readmeContentUpdated ) {
			fs.writeFileSync( readmeFile, readmeContentUpdated );
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
