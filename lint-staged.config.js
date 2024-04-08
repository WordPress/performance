/**
 * External dependencies
 */
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

module.exports = {
	'**/*.js': ( files ) => `npm run lint-js ${ joinFiles( files ) }`,
	'**/*.php': ( files ) => {
		const commands = [ 'composer phpstan' ];

		plugins.forEach( ( plugin ) => {
			const pluginFiles = micromatch(
				files,
				`**/plugins/${ plugin }/**`,
				{ dot: true }
			);

			if ( pluginFiles.length ) {
				commands.push(
					`npm run lint:php:plugins --plugin=${ plugin } ${ joinFiles(
						pluginFiles
					) }`
				);
			}
		} );

		const otherFiles = micromatch( files, `!**/plugins/**`, { dot: true } );

		if ( otherFiles.length ) {
			commands.push( `composer lint ${ joinFiles( otherFiles ) }` );
		}

		return commands;
	},
};
