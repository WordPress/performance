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
		await requestUtils.activateTheme( 'twentytwentyfour' );
		await requestUtils.deactivatePlugin( 'enhanced-responsive-images' );
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
			name: 'core/group',
			attributes: {
				align: 'wide',
			},
			innerBlocks: [
				{
					name: 'core/image',
					attributes: {
						alt: filename,
						id: media.id,
						url: media.source_url,
					},
				},
			],
		} );

		const postId = await editor.publishPost();

		// Navigate to the post and wait for the image to load.
		await page.goto( `/?p=${ postId }` );
		const imageElement = await page.waitForSelector(
			`img.wp-image-${ media.id }`
		);
		const imageUrl = await imageElement.getAttribute( 'src' );
		/* eslint-disable no-console */
		console.log( imageUrl );
		/* eslint-enable no-console */

		// Activate the plugin.
		await requestUtils.activatePlugin( 'enhanced-responsive-images' );

		// Reload the page and wait for the image to load.
		await page.goto( `/?p=${ postId }` );
		const imageElements = await page.waitForSelector(
			`img.wp-image-${ media.id }`
		);
		const newImageUrl = await imageElements.getAttribute( 'src' );
		/* eslint-disable no-console */
		console.log( newImageUrl );
		/* eslint-enable no-console */
	} );
} );
