/**
 * External dependencies
 */
const path = require( 'path' );

/**
 * WordPress dependencies
 */
const { test } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'changing image size', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test( 'should insert and change my image size', async ( {
		admin,
		editor,
		requestUtils,
	} ) => {
		const filename = 'leaves.jpg';
		const filepath = path.join(
			__dirname + '/../../data/images/',
			filename
		);

		await admin.createNewPost();
		const media = await requestUtils.uploadMedia( filepath );

		await editor.insertBlock( {
			name: 'core/image',
			attributes: {
				// Specify alt text so that it can be queried by role selectors.
				alt: filename,
				id: media.id,
				url: media.source_url,
			},
		} );
	} );
} );
