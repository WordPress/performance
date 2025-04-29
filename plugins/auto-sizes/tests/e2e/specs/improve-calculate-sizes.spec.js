/**
 * External dependencies
 */
const path = require( 'path' );

/**
 * WordPress dependencies
 */
const { test } = require( '@wordpress/e2e-test-utils-playwright' );

test.describe( 'check accurate sizes', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activateTheme( 'twentytwentyfour' );
		await requestUtils.deactivatePlugin( 'enhanced-responsive-images' );
		await requestUtils.deleteAllMedia();
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test( 'should get smaller version of image', async ( {
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
		const imageSizes = await imageElement.getAttribute( 'sizes' );

		// Activate the plugin.
		await requestUtils.activatePlugin( 'enhanced-responsive-images' );

		// Reload the page and wait for the image to load.
		await page.goto( `/?p=${ postId }` );
		const updatedImageElement = await page.waitForSelector(
			`img.wp-image-${ media.id }`
		);

		const updatedImageSizes =
			await updatedImageElement.getAttribute( 'sizes' );

		/* eslint-disable no-console */
		console.log( 'image sizes', imageSizes );
		console.log( 'updated image sizes', updatedImageSizes );
		/* eslint-enable no-console */

		if ( imageSizes === updatedImageSizes ) {
			throw new Error(
				'Image sizes did not update after activating the plugin.'
			);
		}

		if ( '(max-width: 620px) 100vw, 620px' !== updatedImageSizes ) {
			throw new Error(
				`Unexpected image sizes: ${ updatedImageSizes }. Expected: (max-width: 620px) 100vw, 620px`
			);
		}

		const currentSrc = await updatedImageElement.evaluate( ( img ) =>
			img instanceof HTMLImageElement ? img.currentSrc : null
		);
		currentSrc.endsWith( 'leaves-768x512.jpg' );
	} );
} );
