/**
 * External dependencies
 */
const path = require( 'path' );

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'changing image size', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test( 'should insert and check image size on front end', async ( {
		admin,
		editor,
		requestUtils,
		page,
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
				alt: filename,
				id: media.id,
				url: media.source_url,
			},
		} );

		const postId = await editor.publishPost();
		await page.goto( `/?p=${ postId }` );

		// Verify the image is present on the frontend
		const imageLocator = page.locator(
			'.entry-content .wp-block-image img'
		);
		await expect( imageLocator ).toHaveAttribute( 'src', media.source_url );
	} );
} );
