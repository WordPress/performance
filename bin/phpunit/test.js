/**
 * External dependencies
 */
const path = require( 'path' );
const { spawnSync } = require( 'child_process' );

/**
 * Internal dependencies
 */
const { plugins } = require( '../../plugins.json' );
const { formats } = require( '../plugin/lib/logger' );

const args = process.argv.slice( 2 );
const { WPP_PLUGIN, WPP_MULTISITE } = process.env;
const pluginBasePath = path.resolve( __dirname, '../../' );
const pluginBaseName = path.basename( pluginBasePath );
const pluginsDir = path.resolve( pluginBasePath, 'plugins' );
const phpunitBin = path.resolve( pluginBasePath, 'vendor/bin/phpunit' );
const wpEnvBin = path.resolve( pluginBasePath, 'node_modules/.bin/wp-env' );
const wpPluginsDir = `/var/www/html/wp-content/plugins/${ pluginBaseName }`;

const _plugins = []; // Store absolute paths to plugins.

if ( WPP_PLUGIN ) {
	if (
		'performance-lab' !== WPP_PLUGIN &&
		! plugins.includes( WPP_PLUGIN )
	) {
		// eslint-disable-next-line no-console
		console.error(
			formats.error(
				`The plugin ${ WPP_PLUGIN } is not a valid plugin managed as part of this project.`
			)
		);

		process.exit( 1 );
	}

	if ( 'performance-lab' === WPP_PLUGIN ) {
		_plugins.push( pluginBasePath );
	} else {
		_plugins.push( path.resolve( pluginsDir, WPP_PLUGIN ) );
	}
} else {
	plugins.forEach( ( plugin ) => {
		_plugins.push( path.resolve( pluginsDir, plugin ) );
	} );
}

const wpEnvRunArgs = {}; // Store plugin name with args provided to wp-env run command.

_plugins.forEach( ( plugin ) => {
	const command = [
		'run',
		'tests-cli',
		`--env-cwd=${ plugin.replace( pluginBasePath, wpPluginsDir ) }`,
	];

	if ( WPP_MULTISITE ) {
		command.push( 'env', 'WP_MULTISITE=1' );
	}

	command.push( phpunitBin.replace( pluginBasePath, wpPluginsDir ) );

	if ( WPP_MULTISITE ) {
		command.push( '--exclude-group', 'ms-excluded' );
	}

	if ( args.length ) {
		command.push( ...args );
	}

	wpEnvRunArgs[ path.basename( plugin ) ] = command;
} );

for ( const [ plugin, command ] of Object.entries( wpEnvRunArgs ) ) {
	if ( Object.keys( wpEnvRunArgs ).length > 1 ) {
		// eslint-disable-next-line no-console
		console.log( formats.info( `\n> Running tests for ${ plugin }` ) );
	}

	// Execute tests synchronously for the sake of async fs.stat in loop.
	// fs.stat is used by wp-env to determine if `/snap` is available on user's system.
	const _process = spawnSync( wpEnvBin, command, { stdio: 'inherit' } );

	if ( _process.signal ) {
		process.exit( 1 );
	}
}
