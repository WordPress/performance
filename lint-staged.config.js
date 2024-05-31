/**
 * External dependencies
 */
const path = require( 'path' );
const micromatch = require( 'micromatch' );

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
	'**/*.js': ( files ) => `npm run lint-js ${ joinFiles( files ) }`,
	'**/*.php': ( files ) => {
		const commands = [ 'composer phpstan' ];

		plugins.forEach( ( plugin ) => {
			const pluginFiles = micromatch(
				files,
				`**/${ PLUGIN_BASE_NAME }/plugins/${ plugin }/**`,
				{ dot: true }
			);

			if ( pluginFiles.length ) {
				commands.push(
					`composer lint:${ plugin } ${ joinFiles( pluginFiles ) }`
				);
			}
		} );

		const otherFiles = micromatch(
			files,
			`!**/${ PLUGIN_BASE_NAME }/plugins/**`,
			{ dot: true }
		);

		if ( otherFiles.length ) {
			commands.push( `composer lint ${ joinFiles( otherFiles ) }` );
		}

		return commands;
	},
};
