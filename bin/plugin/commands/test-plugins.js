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
 * @property {string=} sitetype Site type, 'single' or 'multi'.
 */

/**
 * @typedef WPTestPluginsSettings
 *
 * @property {string} siteType             Site type. 'single' or 'multi'.
 * @property {string} pluginTestAssets     Path to 'plugin-tests' folder.
 * @property {string} builtPluginsDir      Path to 'build' directory.
 * @property {string} wpEnvFile            Path to the plugin tests specific .wp-env.json file.
 * @property {string} wpEnvDestinationFile Path to the final base .wp-env.json file.
 */

exports.options = [
	{
		argname: '-s, --sitetype <sitetype>',
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
		siteType: opt.sitetype || 'single', // Site type.
		pluginTestAssets: './plugin-tests', // plugin test assets.
		builtPluginsDir: './build/', // Built plugins directory.
		wpEnvFile: './plugin-tests/.wp-env.json', // Base .wp-env.json file for testing plugins.
		wpEnvDestinationFile: './.wp-env.json', // Destination .wp-env.json file at root level.
		wpEnvPluginsRegexPattern: '"plugins": \\[(.*)\\],', // Regex to match plugins string in .wp-env.json.
	} );
};

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

	// Buffer built plugins array.
	let builtPlugins = [];

	// Regex object to match wp-env plugins string.
	const wpEnvPluginsRegex = new RegExp( settings.wpEnvPluginsRegexPattern, 'gm' );

	let wpEnvPluginsRegexReplacement = '';

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

	// Only try to read plugin dirs if build directory exists.
	if ( fs.pathExistsSync( settings.builtPluginsDir ) ) {
		// Read all built plugins from build dir.
		try {
			builtPlugins = fs
				.readdirSync( settings.builtPluginsDir, { withFileTypes: true } )
				.filter( ( item ) => item.isDirectory() )
				.map( ( item ) => item.name );
		} catch ( e ) {
			log(
				formats.success(
					`Unable to read plugins from ${ settings.builtPluginsDir }: ${ e }`
				)
			);
		}
	} else {
		log(
			formats.error(
				`Built plugins directory at ${ settings.builtPluginsDir } does not exist. Please run the 'npm run build-plugins' command first.`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// For each built plugin, copy the test.
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
		wpEnvPluginsRegexReplacement = `"plugins": [ "${ builtPlugins
			.map( ( item ) => `${ settings.builtPluginsDir }${ item }` )
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

	// Start the wp-env environment.
	log(
		formats.success(
			`Starting wp-env environment with active plugins: ${ builtPlugins.join(
				', '
			) }`
		)
	);

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

	builtPlugins.forEach( ( plugin ) => {
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

	// If we've reached this far, all tests have passed.
	log(
		formats.success(
			`All standalone plugin tests appeared to have passed :)`
		)
	);

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

	// Return with exit code 0 to trigger a success in the test pipeline.
	process.exit( 0 );
}
