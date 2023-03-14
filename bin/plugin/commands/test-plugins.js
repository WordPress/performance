/**
 * External dependencies
 */
const fs = require( 'fs-extra' );
const { execSync, spawnSync } = require( 'node:child_process' );

/**
 * Internal dependencies
 */
const { log, formats } = require( '../lib/logger' );

exports.options = [];

/**
 * Command for testing all built stndalone plugins.
 */
exports.handler = async () => {
	// plugin test assets.
	const pluginTestAssets = './plugin-tests';

	// Built plugins directory.
	const builtPluginsDir = './build/';

	// Buffer built plugins array.
	let builtPlugins = [];

	// root .wp-env.json file.
	const wpEnvFile = '.wp-env.json';

	// Regex to match plugins string in .wp-env.json.
	const wpEnvPluginsRegexPattern = '"plugins": \\[(.*)\\],';

	// Regex object to match wp-env plugins string.
	const wpEnvPluginsRegex = new RegExp( wpEnvPluginsRegexPattern, 'gm' );

	let wpEnvPluginsRegexReplacement = '';

	// If the root .wp-env.json file is missing, abort.
	if ( ! fs.pathExistsSync( wpEnvFile ) ) {
		log(
			formats.error(
				`WP Env config file "${ wpEnvFile }" not detected in root of project.`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// Only try to read plugin dirs if build ir exists.
	if ( fs.pathExistsSync( builtPluginsDir ) ) {
		// Read all built plugins from build dir.
		try {
			builtPlugins = fs
				.readdirSync( builtPluginsDir, { withFileTypes: true } )
				.filter( ( item ) => item.isDirectory() )
				.map( ( item ) => item.name );
		} catch ( e ) {
			log(
				formats.success(
					`Unable to read plugins from ${ builtPluginsDir }: ${ e }`
				)
			);
		}
	} else {
		log(
			formats.error(
				`Built plugins directory at ${ builtPluginsDir } does not exist. Please run the 'npm run build-plugins' command first.`
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
			fs.copySync( pluginTestAssets, `./build/${ plugin }/`, {
				overwrite: true,
			} );
			log(
				formats.success(
					`Copied test assets for plugin "${ plugin }, executing "composer install --no-interaction" on plugin.\n`
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
			`composer install --working-dir=${ builtPluginsDir }${ plugin } --no-interaction`,
			( err, output ) => {
				// once the command has completed, the callback function is called
				if ( err ) {
					log( formats.error( `${ err }` ) );
					return;
				}
				// log the output received from the command
				log( output );
			}
		);
	} );

	// Amend wp-env.json to reference built plugins only.
	log(
		formats.success(
			`\nTest assets copied and "composer install" executed for all built plugins, configuring .wp-env.json file to reference built plugins as active plugins. Plugins to be enabled are: ${ builtPlugins.join(
				', '
			) }`
		)
	);

	// Buffer .wp-env.json content var.
	let wpEnvFileContent = '';

	try {
		wpEnvFileContent = fs.readFileSync( wpEnvFile, 'utf-8' );
	} catch ( e ) {
		log( formats.error( `Error reading file "${ wpEnvFile }": "${ e }"` ) );

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// If the contents of the file were incorrectly read or exception was not captured and value is blank, abort.
	if ( '' === wpEnvFileContent ) {
		log(
			formats.error(
				`File content for "${ wpEnvFile }" is empty, aborting.`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// If we do not have a match on the wp-env enabled plugins regex, abort.
	if ( ! wpEnvPluginsRegex.test( wpEnvFileContent ) ) {
		log(
			formats.error(
				`Unable to find plugins property/key in WP Env config file: "${ wpEnvFile }". Please ensure that it is present and try agagin.`
			)
		);

		// Return with exit code 1 to trigger a failure in the test pipeline.
		process.exit( 1 );
	}

	// Let the user know we're re-writing the .wp-env.json file.
	log( formats.success( `Rewriting plugins property in ${ wpEnvFile }` ) );

	// Attempt replacement of the plugins property in .wp-env.json file to match built plugins.
	try {
		// Create plugins property from built plugins.
		wpEnvPluginsRegexReplacement = `"plugins": [ "${ builtPlugins
			.map( ( item ) => `${ builtPluginsDir }${ item }` )
			.join( '", "' ) }" ],`;

		fs.writeFileSync(
			wpEnvFile,
			wpEnvFileContent.replace(
				wpEnvPluginsRegex,
				wpEnvPluginsRegexReplacement
			)
		);
	} catch ( e ) {
		log(
			formats.error(
				`Error replacing content in ${ wpEnvFile } using regex "${ wpEnvPluginsRegex }": "${ e }"`
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
	execSync( `wp-env start`, ( err, output ) => {
		// once the command has completed, the callback function is called
		if ( err ) {
			log( formats.error( `${ err }` ) );

			// Return with exit code 1 to trigger a failure in the test pipeline.
			process.exit( 1 );
		}
		// log the output received from the command
		log( output );
	} );

	// Run tests per plugin.
	log(
		formats.success(
			`wp-env is running, running composer install on main composer container`
		)
	);

	// Run composer install on the main coposer container for the project.
	execSync( `npm run pretest-php`, ( err, output ) => {
		// once the command has completed, the callback function is called
		if ( err ) {
			log( formats.error( `${ err }` ) );

			// Return with exit code 1 to trigger a failure in the test pipeline.
			process.exit( 1 );
		}
		// log the output received from the command
		log( output );
	} );

	// Run tests per plugin.
	log( formats.success( `About to execute standalone plugins tests.` ) );

	builtPlugins.forEach( ( plugin ) => {
		log(
			formats.success(
				`Running plugin integration tests for plugin: "${ plugin }"`
			)
		);

		const command = spawnSync(
			'wp-env',
			[
				'run',
				'phpunit',
				`'phpunit -c /var/www/html/wp-content/plugins/${ plugin }/phpunit.xml.dist --verbose --testdox'`,
			],
			{ shell: true, encoding: 'utf8' }
		);

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

		// Run phpunit tests for specific plugin.
		try {
			execSync(
				`wp-env run phpunit 'phpunit -c /var/www/html/wp-content/plugins/${ plugin }/phpunit.xml.dist --verbose --testdox'`,
				( err, output ) => {
					// once the command has completed, the callback function is called
					if ( err ) {
						log(
							formats.error(
								`Error executing phpunit test command: ${ err }`
							)
						);

						// Return with exit code 1 to trigger a failure in the test pipeline.
						process.exit( 1 );
					}
					// log the output received from the command
					log( output );
				}
			);
		} catch ( e ) {
			log(
				formats.error(
					`One or more tests failed for plugin "${ plugin }"`
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
		// once the command has completed, the callback function is called
		if ( err ) {
			log( formats.error( `${ err }` ) );
			return;
		}
		// log the output received from the command
		log( output );
	} );
};
