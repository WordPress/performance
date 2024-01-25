/**
 * External dependencies
 */
const fs = require( 'fs-extra' );
const { execSync, spawnSync } = require( 'child_process' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );

/**
 * @typedef WPTestPluginsCommandOptions
 *
 * @property {string=} siteType Optional site type, 'single' or 'multi'. Defaults to single.
 */

/**
 * @typedef WPDoReplaceWpEnvContent
 *
 * @property {string} wpEnvPluginsRegexPattern Regex to match against plugins property of .wp-env.json.
 * @property {string} wpEnvFile                Path to the plugin tests specific .wp-env.json file.
 * @property {string} wpEnvDestinationFile     Path to the final base .wp-env.json file.
 * @property {Object} builtPlugins             Array of plugins slugs from plugins json file.
 */

/**
 * @typedef WPDoRunUnitTests
 *
 * @property {string} siteType             Site type. 'single' or 'multi'.
 * @property {Object} builtPlugins         Array of plugin slugs from plugins json file.
 * @property {Object} disablePlugins       Array of plugin slugs to deactivate on test run.
 * @property {Object} enablePlugins        Array of plugin slugs to activate on test run.
 * @property {string} wpEnvDestinationFile Path to the final base .wp-env.json file.
 */

/**
 * @typedef WPTestPluginsSettings
 *
 * @property {string} pluginsJsonFile       Path to the plugins.json file used for building plugins.
 * @property {string} siteType              Site type. 'single' or 'multi'.
 * @property {string} pluginTestAssets      Path to 'plugin-tests' folder.
 * @property {string} builtPluginsDir       Path to 'build' directory.
 * @property {string} wpEnvFile             Path to the plugin tests specific .wp-env.json file.
 * @property {string} wpEnvDestinationFile  Path to the final base .wp-env.json file.
 * @property {string} performancePluginSlug Slug of the main WPP plugin.
 */

exports.options = [
	{
		argname: '-s, --sitetype <siteType>',
		description: 'Whether to test "single" (default) or "multi" site.',
	},
];

// Switch for flagging start of wp-env so it is not started multiple times.
let isWpEnvStarted = false;

// The Current directory from which the script is executed.
// This should always be base plugin folder.
const baseDirectory = process.cwd().match( /([^\/]*)\/*$/ )[ 1 ];

/**
 * Command for testing all built standalone plugins.
 *
 * @param {WPTestPluginsCommandOptions} opt Command options.
 */
exports.handler = async ( opt ) => {
	doRunStandalonePluginTests( {
		pluginsJsonFile: './plugins.json', // Path to plugins.json file.
		siteType: opt.sitetype || 'single', // Site type.
		pluginTestAssets: './plugin-tests', // plugin test assets.
		builtPluginsDir: './build/', // Built plugins directory.
		wpEnvFile: './plugin-tests/.wp-env.json', // Base .wp-env.json file for testing plugins.
		wpEnvDestinationFile: './.wp-env.override.json', // Destination .wp-env.override.json file at root level.
		wpEnvPluginsRegexPattern: '"plugins": \\[(.*)\\],', // Regex to match plugins string in .wp-env.json.
		performancePluginSlug: baseDirectory, // The plugin slug of the main WPP plugin, should be the base directory.
	} );
};

/**
 * Handles replacement of plugins array in .wp-env.json file.
 * Split into separate function in order to easily re-run tests with WPP plugin active.
 *
 * @param {WPDoReplaceWpEnvContent} settings Plugin test settings.
 */
function doReplaceWpEnvContent( settings ) {
	// Regex object to match wp-env plugins string.
	const wpEnvPluginsRegex = new RegExp(
		settings.wpEnvPluginsRegexPattern,
		'gm'
	);

	let wpEnvPluginsRegexReplacement = '';

	// Amend wp-env overrides file to reference built plugins only.
	// Buffer .wp-env.json content var.
	let wpEnvFileContent = '';

	try {
		wpEnvFileContent = fs.readFileSync( settings.wpEnvFile, 'utf-8' );
	} catch ( e ) {
		log(
			formats.error(
				`Error reading file "${ settings.wpEnvFile }": "${ e }"`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// If the contents of the file were incorrectly read or exception was not captured and value is blank, abort.
	if ( '' === wpEnvFileContent ) {
		log(
			formats.error(
				`File content for "${ settings.wpEnvFile }" is empty, aborting.`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// If we do not have a match on the wp-env enabled plugins regex, abort.
	if ( ! wpEnvPluginsRegex.test( wpEnvFileContent ) ) {
		log(
			formats.error(
				`Unable to find plugins property/key in WP Env config file: "${ settings.wpEnvFile }". Please ensure that it is present and try again.`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// Copy the newly modified wp-env overrides file to the root level if it does not exist.
	if ( ! fs.pathExistsSync( settings.wpEnvDestinationFile ) ) {
		try {
			fs.copySync( settings.wpEnvFile, settings.wpEnvDestinationFile, {
				overwrite: true,
			} );
			log(
				formats.success(
					`Copied wp-env overrides file to root level, ready to rewrite plugins.`
				)
			);
		} catch ( e ) {
			log(
				formats.error(
					`Error copying wp-env overrides file at "${ settings.wpEnvFile } to root level at "${ settings.wpEnvDestinationFile }". ${ e }`
				)
			);

			// Return with exit code 1 to trigger a failure in the test pipeline.
			process.exit( 1 );
		}
	}

	// Let the user know we're re-writing the .wp-env.json file.
	log(
		formats.success(
			`Rewriting plugins property in ${ settings.wpEnvDestinationFile }`
		)
	);

	// Attempt replacement of the plugins property in .wp-env.json file to match built plugins.
	try {
		// Create plugins property from built plugins.
		wpEnvPluginsRegexReplacement = `"plugins": [ "${ settings.builtPlugins
			.map( ( item ) => {
				if ( '.' === item ) {
					// Do not append build dir for root plugin.
					return item;
				}

				return `${ settings.builtPluginsDir }${ item }`;
			} )
			.join( '", "' ) }" ],`;

		fs.writeFileSync(
			settings.wpEnvDestinationFile,
			wpEnvFileContent.replace(
				wpEnvPluginsRegex,
				wpEnvPluginsRegexReplacement
			)
		);
	} catch ( e ) {
		log(
			formats.error(
				`Error replacing content in ${ settings.wpEnvDestinationFile } using regex "${ wpEnvPluginsRegex }": "${ e }"`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}
}
/**
 * Handles starting of wp-env, running unit tests and stopping of wp-env.
 * Split into separate function in order to easily re-run tests with WPP plugin active.
 *
 * @param {WPDoRunUnitTests} settings Unit testing settings.
 */
function doRunUnitTests( settings ) {
	// Only start the wp-env environment if it is not already started.
	if ( false === isWpEnvStarted ) {
		// Start the wp-env environment.
		log(
			formats.success(
				`Starting wp-env environment with active plugins: ${ settings.builtPlugins.join(
					', '
				) }`
			)
		);

		// Exclude the main WPP plugin from actual traversion into build folder during testing.
		if ( '.' === settings.builtPlugins[ 0 ] ) {
			settings.builtPlugins.shift();
		}

		// Execute wp-env start.
		execSync( `npm run wp-env start`, ( err, output ) => {
			// once the command has completed, the callback function is called.
			if ( err ) {
				log( formats.error( `${ err }` ) );

				// Return with exit code 1 to trigger a failure in the test pipeline.
				process.exit( 1 );
			}

			// log the output received from the command.
			log( output );
		} );

		// Flag that the env is now started.
		isWpEnvStarted = true;

		// Remove the wp-env overrides file.
		try {
			fs.unlinkSync( settings.wpEnvDestinationFile );
		} catch ( error ) {
			log(
				formats.error(
					`Error deleting file: ${ settings.wpEnvDestinationFile }. ${ error }`
				)
			);
		}
	}

	// If there is the presence of an disablePlugins array, disable said plugins prior to testing.
	if (
		'undefined' !== typeof settings?.disablePlugins &&
		settings.disablePlugins.constructor === Array &&
		settings.disablePlugins.length > 0
	) {
		log(
			formats.success(
				`Detected plugins that need deactivating prior to running tests: ${ settings.disablePlugins.join(
					', '
				) }`
			)
		);
		settings.disablePlugins.forEach( ( plugin ) => {
			// Disable plugin via wp-cli.
			execSync(
				`wp-env run cli wp plugin deactivate ${ plugin }`,
				( err, output ) => {
					// once the command has completed, the callback function is called.
					if ( err ) {
						log( formats.error( `${ err }` ) );
						return;
					}
					// log the output received from the command.
					log( output );
				}
			);
		} );
	}

	// If there is the presence of an enablePlugins array, enable said plugins prior to testing.
	if (
		'undefined' !== typeof settings?.enablePlugins &&
		settings.enablePlugins.constructor === Array &&
		settings.enablePlugins.length > 0
	) {
		log(
			formats.success(
				`Detected plugins that need activating prior to running tests: ${ settings.enablePlugins.join(
					', '
				) }`
			)
		);
		settings.enablePlugins.forEach( ( plugin ) => {
			// Disable plugin via wp-cli.
			execSync(
				`wp-env run cli wp plugin activate ${ plugin }`,
				( err, output ) => {
					// once the command has completed, the callback function is called.
					if ( err ) {
						log( formats.error( `${ err }` ) );
						return;
					}
					// log the output received from the command.
					log( output );
				}
			);
		} );
	}

	// Run tests per plugin.
	settings.builtPlugins.forEach( ( plugin ) => {
		log(
			formats.success(
				`Running plugin integration tests for plugin: "${ plugin }"`
			)
		);

		// Define multi site flag based on single vs multi sitetype arg.
		const isMultiSite = 'multi' === settings.siteType;
		let command = '';

		if ( isMultiSite ) {
			command = spawnSync(
				'wp-env',
				[
					'run',
					'tests-cli',
					`--env-cwd=/var/www/html/wp-content/plugins/${ plugin } vendor/bin/phpunit -c multisite.xml --verbose --testdox`,
				],
				{ shell: true, encoding: 'utf8' }
			);
		} else {
			command = spawnSync(
				'wp-env',
				[
					'run',
					'tests-cli',
					`--env-cwd=/var/www/html/wp-content/plugins/${ plugin } vendor/bin/phpunit -c phpunit.xml --verbose --testdox`,
				],
				{ shell: true, encoding: 'utf8' }
			);
		}

		if ( command.stderr ) {
			log( formats.error( command.stderr.replace( '\n', '' ) ) );
		}

		log( command.stdout.replace( '\n', '' ) );

		if ( 1 === command.status ) {
			log(
				formats.error(
					`One or more tests failed for plugin ${ plugin }`
				)
			);

			// Return with exit code 1 to trigger a failure in the test pipeline.
			process.exit( 1 );
		}
	} );
}

/**
 * Runs standalone plugin tests in single or multisite environments.
 *
 * @param {WPTestPluginsSettings} settings Plugin test settings.
 */
function doRunStandalonePluginTests( settings ) {
	// Check if the siteType arg is one of single or multi.
	if ( 'single' !== settings.siteType && 'multi' !== settings.siteType ) {
		log(
			formats.error( `--sitetype must be one of "single" or "multi".` )
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// If the base .wp-env.json file for testing plugins is missing, abort.
	if ( ! fs.pathExistsSync( settings.wpEnvFile ) ) {
		log(
			formats.error(
				`WP Env config file "${ settings.wpEnvFile }" not detected in root of project.`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// Buffer built plugins array.
	let builtPlugins = [];

	// Buffer contents of plugins JSON file.
	let pluginsJsonFileContent = '';

	try {
		pluginsJsonFileContent = fs.readFileSync(
			settings.pluginsJsonFile,
			'utf-8'
		);
	} catch ( e ) {
		log(
			formats.error(
				`Error reading file at "${ settings.pluginsJsonFile }". ${ e }`
			)
		);
	}

	// Validate that the plugins JSON file contains content before proceeding.
	if ( '' === pluginsJsonFileContent || ! pluginsJsonFileContent ) {
		log(
			formats.error(
				`Contents of file at "${ settings.pluginsJsonFile }" could not be read, or are empty.`
			)
		);
	}

	const pluginsJsonFileContentAsJson = JSON.parse( pluginsJsonFileContent );

	// Check for valid and not empty object resulting from plugins JSON file parse.
	if (
		'object' !== typeof pluginsJsonFileContentAsJson ||
		0 === Object.keys( pluginsJsonFileContentAsJson ).length
	) {
		log(
			formats.error(
				`File at "settings.pluginsJsonFile" parsed, but detected empty/non valid JSON object.`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// Create an array of plugins from entries in plugins JSON file.
	builtPlugins = Object.keys( pluginsJsonFileContentAsJson )
		.filter( ( item ) => {
			if (
				! fs.pathExistsSync(
					`${ settings.builtPluginsDir }${ pluginsJsonFileContentAsJson[ item ].slug }`
				)
			) {
				log(
					formats.error(
						`Built plugin path "${ settings.builtPluginsDir }${ pluginsJsonFileContentAsJson[ item ].slug }" not found, skipping and removing from plugin list`
					)
				);
				return false;
			}
			return true;
		} )
		.map( ( item ) => pluginsJsonFileContentAsJson[ item ].slug );

	// For each built plugin, copy the test assets.
	builtPlugins.forEach( ( plugin ) => {
		log(
			formats.success(
				`Detected built plugin "${ plugin }", copying test files for standalone/integration testing.`
			)
		);

		// Copy over test files.
		try {
			fs.copySync(
				settings.pluginTestAssets,
				`${ settings.builtPluginsDir }${ plugin }/`,
				{
					overwrite: true,
				}
			);
			log(
				formats.success(
					`Copied test assets for plugin "${ plugin }", executing "composer update --no-interaction" on plugin.\n`
				)
			);
		} catch ( e ) {
			log(
				formats.error(
					`Error copying test assets for plugin "${ plugin }". ${ e }`
				)
			);

			// Return with exit code 1 to trigger a failure in the test pipeline.
			process.exit( 1 );
		}

		// Execute composer update within built plugin following copy.
		// Ensures PHPUnit is downgraded/upgrades as necessary.
		const command = spawnSync(
			'wp-env',
			[
				'run',
				'tests-cli',
				`--env-cwd=/var/www/html/wp-content/plugins/${ plugin } composer update --no-interaction`,
			],
			{ shell: true, encoding: 'utf8' }
		);

		if ( command.stderr ) {
			log( formats.error( command.stderr.replace( '\n', '' ) ) );
		}

		log( command.stdout.replace( '\n', '' ) );
	} );

	// Add the root level WPP plugin to the built plugins array.
	// This allows us tests with WPP plugin active if desired.
	builtPlugins.unshift( '.' );

	// Handle replacement of wp-env file content for round 1 of testing without root plugin.
	doReplaceWpEnvContent( { ...settings, builtPlugins } );

	// Run unit tests with main WPP plugin disabled.
	const disablePlugins = [ settings.performancePluginSlug ];

	log( 'Running integration tests with main WPP plugin inactive.' );
	doRunUnitTests( { ...settings, builtPlugins, disablePlugins } );

	// Re-run unit tests against built plugins, with WPP plugin active as well.
	const enablePlugins = [ settings.performancePluginSlug ];

	log( 'Running integration tests with main WPP plugin active.' );
	doRunUnitTests( { ...settings, builtPlugins, enablePlugins } );

	// If we've reached this far, all tests have passed.
	log(
		formats.success(
			`All standalone plugin tests appeared to have passed.`
		)
	);

	// Stop wp-env.
	execSync( `wp-env stop`, ( err, output ) => {
		// once the command has completed, the callback function is called.
		if ( err ) {
			log( formats.error( `${ err }` ) );
			return;
		}
		// log the output received from the command.
		log( output );
	} );

	// Return with exit code 0 to trigger a success in the test pipeline.
	process.exit( 0 );
}
