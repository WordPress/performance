/**
 * External dependencies
 */
const path = require( 'path' );
const micromatch = require( 'micromatch' );
const { execSync } = require( 'child_process' );

/**
 * Internal dependencies
 */
const { plugins } = require( './plugins.json' );

/**
 * Join and escape filenames for shell.
 *
 * @param {string[]} files Files to join.
 *
 * @return {string} Joined files.
 */
const joinFiles = ( files ) => {
	return files.map( ( file ) => `'${ file }'` ).join( ' ' );
};

// Get plugin base name to match regex more accurately.
// Else it can cause issues when this plugin is placed in `wp-content/plugins` directory.
const PLUGIN_BASE_NAME = path.basename( __dirname );

module.exports = {
	'**/*.js': ( files ) => `npm run lint-js -- ${ joinFiles( files ) }`,
	'**/*.php': ( files ) => {
		const getAllTrackedFiles = ( pattern ) => {
			return execSync( `git ls-files ${ pattern }` )
				.toString()
				.trim()
				.split( '\n' );
		};

		const commands = [];

		plugins.forEach( ( plugin ) => {
			const pluginFiles = micromatch(
				files,
				`**/${ PLUGIN_BASE_NAME }/plugins/${ plugin }/**`,
				{ dot: true }
			);

			if ( pluginFiles.length ) {
				const allPhpFiles = getAllTrackedFiles(
					`plugins/${ plugin }`
				).filter( ( file ) => /\.php$/.test( file ) );
				if ( allPhpFiles.length ) {
					commands.push(
						`composer phpstan -- ${ joinFiles( allPhpFiles ) }`
					);
				}

				// Note: The lint command has to be used directly because the plugin-specific lint command includes the entire plugin directory as an argument.
				commands.push(
					`composer lint -- --standard=./plugins/${ plugin }/phpcs.xml.dist ${ joinFiles(
						pluginFiles
					) }`
				);
			}
		} );

		const otherFiles = micromatch(
			files,
			`!**/${ PLUGIN_BASE_NAME }/plugins/**`,
			{ dot: true }
		);

		if ( otherFiles.length ) {
			const allPhpFiles = getAllTrackedFiles( `!(plugins)` ).filter(
				( file ) => /\.php$/.test( file )
			);
			if ( allPhpFiles.length ) {
				commands.push(
					`composer phpstan -- ${ joinFiles( allPhpFiles ) }`
				);
			}

			commands.push( `composer lint -- ${ joinFiles( otherFiles ) }` );
		}

		return commands;
	},
};
