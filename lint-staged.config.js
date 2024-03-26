/**
 * External dependencies
 */
const micromatch = require( 'micromatch' );

/**
 * Internal dependencies
 */
const { plugins } = require( './plugins.json' );

module.exports = {
	'**/*.js': ( files ) =>
		`npm run lint-js ${ files.length > 10 ? '' : files.join( ' ' ) }`,
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
						pluginFiles.length > 10 ? '' : pluginFiles.join( ' ' )
					}`
				);
			}
		} );

		const otherFiles = micromatch( files, `!plugins/**` );

		if ( otherFiles.length ) {
			commands.push(
				`npm run lint:php ${
					otherFiles.length > 10 ? '' : otherFiles.join( ' ' )
				}`
			);
		}

		return commands;
	},
};
