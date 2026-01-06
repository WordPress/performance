/**
 * External dependencies
 */
const fs = require( 'fs' );

/**
 * Internal dependencies
 */
const { plugins } = require( './plugins.json' );

/**
 * @type {import('lint-staged').Configuration}
 */
const config = {
	'**/*.{js,ts,mjs}': [ 'npm run lint-js', () => 'npm run tsc' ],
	'**/*.json': [ 'node bin/validate-json-schema.js' ],
	'**/*.php': () => 'composer phpstan',
	'*.php': 'composer lint',
	'/tools/**.php': 'composer lint',
	// Note: Instead of the preceding two lines, the following line was tried but it is not working:
	// [ `!(plugins/{${ plugins.join( '|' ) }})/**/*.php` ]: 'composer lint',
	'composer.{json,lock}': () => 'composer validate --strict',
};

for ( const plugin of plugins ) {
	const phpcsConfig = fs.existsSync( `plugins/${ plugin }/phpcs.xml` )
		? `plugins/${ plugin }/phpcs.xml`
		: `plugins/${ plugin }/phpcs.xml.dist`;
	config[
		`plugins/${ plugin }/**/*.php`
	] = `composer lint -- --standard=${ phpcsConfig }`;
}

module.exports = config;
