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
 * @param {Array} files Files to join.
 *
 * @return {string} Joined files.
 */
const joinFiles = ( files ) => {
	return files.map( ( file ) => `'${ file }'` ).join( ' ' );
};

module.exports = {
	'**/*.js': ( files ) =>
		`npm run lint-js ${ files.length > 10 ? '' : joinFiles( files ) }`,
	'**/*.php': ( files ) => {
		const commands = [ 'composer phpstan' ];

		plugins.forEach( ( plugin ) => {
			const pluginFiles = micromatch(
				files,
				`**/plugins/${ plugin }/**`
			);

			if ( pluginFiles.length ) {
				commands.push(
					`npm run lint:php:${ plugin } ${
						pluginFiles.length > 10 ? '' : joinFiles( pluginFiles )
					}`
				);
			}
		} );

		const otherFiles = micromatch( files, `!plugins/**` );

		if ( otherFiles.length ) {
			commands.push(
				`npm run lint:php ${
					otherFiles.length > 10 ? '' : joinFiles( otherFiles )
				}`
			);
		}

		return commands;
	},
};
