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
 * @property {string} siteType     Site type. 'single' or 'multi'.
 * @property {Object} builtPlugins Array of plugins slugs from plugins json file.
 */

/**
 * @typedef WPTestPluginsSettings
 *
 * @property {string} pluginsJsonFile      Path to the plugins.json file used for building plugins.
 * @property {string} siteType             Site type. 'single' or 'multi'.
 * @property {string} pluginTestAssets     Path to 'plugin-tests' folder.
 * @property {string} builtPluginsDir      Path to 'build' directory.
 * @property {string} wpEnvFile            Path to the plugin tests specific .wp-env.json file.
 * @property {string} wpEnvDestinationFile Path to the final base .wp-env.json file.
 */

exports.options = [
	{
		argname: '-s, --sitetype <siteType>',
		description: 'Whether to test "single" (default) or "multi" site.',
	},
];

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
	const wpEnvPluginsRegex = new RegExp( settings.wpEnvPluginsRegexPattern, 'gm' );

	let wpEnvPluginsRegexReplacement = '';
	// Amend wp-env.json to reference built plugins only.
	// Buffer .wp-env.json content var.
	let wpEnvFileContent = '';

	try {
		wpEnvFileContent = fs.readFileSync( settings.wpEnvFile, 'utf-8' );
	} catch ( e ) {
		log( formats.error( `Error reading file "${ settings.wpEnvFile }": "${ e }"` ) );

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
				`Unable to find plugins property/key in WP Env config file: "${ settings.wpEnvFile }". Please ensure that it is present and try agagin.`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// Let the user know we're re-writing the .wp-env.json file.
	log( formats.success( `Rewriting plugins property in ${ settings.wpEnvFile }` ) );

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
			settings.wpEnvFile,
			wpEnvFileContent.replace(
				wpEnvPluginsRegex,
				wpEnvPluginsRegexReplacement
			)
		);
	} catch ( e ) {
		log(
			formats.error(
				`Error replacing content in ${ settings.wpEnvFile } using regex "${ wpEnvPluginsRegex }": "${ e }"`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// Copy the newly modified ./plugins-tests/.wp-env.json file to the root level.
	try {
		fs.copySync( settings.wpEnvFile, settings.wpEnvDestinationFile, {
			overwrite: true,
		} );
		log(
			formats.success(
				`Copied modified .wp-env.json file to root level, ready to start wp-env.`
			)
		);
	} catch ( e ) {
		log(
			formats.error(
				`Error copying modified .wp-env.json file at "${ settings.wpEnvFile } to root level". ${ e }`
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

	// Run tests per plugin.
	log(
		formats.success(
			`wp-env is running, running composer install on main composer container`
		)
	);

	settings.builtPlugins.forEach( ( plugin ) => {
		log(
			formats.success(
				`Running plugin integration tests for plugin: "${ plugin }"`
			)
		);

		// Define multi site flag based on single vs multi sitetype arg.
		const isMutiSite = 'multi' === settings.siteType;
		let command = '';

		if ( isMutiSite ) {
			command = spawnSync(
				'wp-env',
				[
					'run',
					'phpunit',
					`'WP_MULTISITE=1 phpunit -c /var/www/html/wp-content/plugins/${ plugin }/multisite.xml --verbose --testdox'`,
				],
				{ shell: true, encoding: 'utf8' }
			);
		} else {
			command = spawnSync(
				'wp-env',
				[
					'run',
					'phpunit',
					`'phpunit -c /var/www/html/wp-content/plugins/${ plugin }/phpunit.xml --verbose --testdox'`,
				],
				{ shell: true, encoding: 'utf8' }
			);
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

	// Start winding down.
	log( `Stopping wp-env...` );

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
}

/**
 * Runs standalone plugin tests in single or multisite environments.
 *
 * @param {WPTestPluginsSettings} settings Plugin test settings.
 */
function doRunStandalonePluginTests( settings ) {
	// Check if the siteType arg is one of single or multi.
	if (
		'single' !== settings.siteType &&
		'multi' !== settings.siteType
	) {
		log(
			formats.error(
				`--sitetype must be one of "single" or "multi".`
			)
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
		pluginsJsonFileContent = fs.readFileSync( settings.pluginsJsonFile, 'utf-8' );
	} catch ( e ) {
		log(
			formats.error( `Error reading file at "${ settings.pluginsJsonFile }". ${ e }` )
		);
	}

	// Validate that the plugins JSON file contains content before proceeding.
	if (
		'' === pluginsJsonFileContent ||
		! pluginsJsonFileContent
	) {
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
			if ( ! fs.pathExistsSync( `${ settings.builtPluginsDir }${ pluginsJsonFileContentAsJson[ item ].slug }` ) ) {
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
			fs.copySync( settings.pluginTestAssets, `${ settings.builtPluginsDir }${ plugin }/`, {
				overwrite: true,
			} );
			log(
				formats.success(
					`Copied test assets for plugin "${ plugin }", executing "composer install --no-interaction" on plugin.\n`
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

		// Execute composer install within built plugin following copy.
		execSync(
			`composer install --working-dir=${ settings.builtPluginsDir }${ plugin } --no-interaction`,
			( err, output ) => {
				if ( err ) {
					log( formats.error( `${ err }` ) );
					process.exit( 1 );
				}
				// log the output received from the command
				log( output );
			}
		);
	} );

	// Handle replacement of wp-env file content for round 1 of testing without root plugin.
	doReplaceWpEnvContent( { ...settings, builtPlugins } );

	// Run unit tests against built plugins.
	doRunUnitTests( { ...settings, builtPlugins } );

	log(
		formats.success(
			`Re-running standalone plugin integration tests with WPP plugin active.`
		)
	);
	// Add the root level WPP plugin to the built plugins array.
	// This allows to re-run tests with WPP plugin active.
	builtPlugins.unshift( '.' );

	// Handle replacement of wp-env file content for round 2 of testing with root plugin.
	doReplaceWpEnvContent( { ...settings, builtPlugins } );

	// Re-run unit tests against built plugins, with WPP plugin active as well.
	doRunUnitTests( { ...settings, builtPlugins } );

	// If we've reached this far, all tests have passed.
	log(
		formats.success(
			`All standalone plugin tests appeared to have passed.`
		)
	);

	// Return with exit code 0 to trigger a success in the test pipeline.
	process.exit( 0 );
}
