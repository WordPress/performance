/* jshint node: true */
// ^ This Playwright spec runs in Node (CommonJS), not the browser like the
// sibling plugin scripts that the `plugins/.jshintrc` browser config targets.

/**
 * External dependencies
 */
const path = require( 'path' );
const fs = require( 'fs/promises' );

/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

/**
 * @typedef {import('@playwright/test').Page} Page
 * @typedef {import('@wordpress/e2e-test-utils-playwright').Admin} Admin
 * @typedef {import('@wordpress/e2e-test-utils-playwright').Editor} Editor
 * @typedef {import('@wordpress/e2e-test-utils-playwright').RequestUtils} RequestUtils
 *
 * @typedef {{ file: string, filesize: number }} Source
 * @typedef {{ file: string, mime_type: string, source_url: string, sources?: Record<string, Source> }} Size
 * @typedef {{
 *     id: number,
 *     mime_type: string,
 *     source_url: string,
 *     media_details: { file: string, original_image?: string, sources?: Record<string, Source>, sizes: Record<string, Size> }
 * }} Attachment
 * @typedef {{ name: string, mimeType: string, buffer: Buffer }} Upload
 */

const IMAGES_DIR = path.join( __dirname, '..', '..', 'data', 'images' );

/**
 * Saves the Modern Image Formats settings on the Media Settings screen.
 *
 * @param {Admin}   admin    Admin utils.
 * @param {Page}    page     Playwright page.
 * @param {boolean} fallback Whether to output fallback images.
 */
async function saveSettings( admin, page, fallback ) {
	await admin.visitAdminPage( 'options-media.php' );
	await page.locator( '#perflab_modern_image_format' ).selectOption( 'avif' );
	await page
		.locator( '#perflab_generate_webp_and_jpeg' )
		.setChecked( fallback );
	if ( fallback ) {
		await page.locator( '#webp_uploads_use_picture_element' ).check();
	}
	await page.locator( '#submit' ).click();
	await expect(
		page.locator( '#setting-error-settings_updated' )
	).toBeVisible();
}

/**
 * Reads a test image from the plugin's test data.
 *
 * @param {string} fileName File name of the image.
 * @param {string} mimeType Mime type of the image.
 * @return {Promise<Upload>} The image to upload.
 */
async function readImage( fileName, mimeType ) {
	return {
		name: fileName,
		mimeType,
		buffer: await fs.readFile( path.join( IMAGES_DIR, fileName ) ),
	};
}

/**
 * Draws a JPEG which the browser encodes to a smaller AVIF, unlike the photos in the test data.
 *
 * @param {Page}   page     Playwright page.
 * @param {string} fileName File name of the image.
 * @param {number} width    Width of the image in pixels.
 * @param {number} height   Height of the image in pixels.
 * @return {Promise<Upload>} The image to upload.
 */
async function drawImage( page, fileName, width, height ) {
	const dataUrl = await page.evaluate(
		( [ w, h ] ) => {
			const canvas = document.createElement( 'canvas' );
			canvas.width = w;
			canvas.height = h;
			const context = /** @type {CanvasRenderingContext2D} */ (
				canvas.getContext( '2d' )
			);
			const gradient = context.createLinearGradient( 0, 0, w, h );
			gradient.addColorStop( 0, '#1d4ed8' );
			gradient.addColorStop( 0.5, '#f59e0b' );
			gradient.addColorStop( 1, '#be123c' );
			context.fillStyle = gradient;
			context.fillRect( 0, 0, w, h );
			context.fillStyle = '#ffffff';
			for ( let i = 0; i < 40; i++ ) {
				context.beginPath();
				context.arc(
					( w / 40 ) * i,
					h / 2 + ( h / 3 ) * Math.sin( i ),
					w / 50,
					0,
					7
				);
				context.fill();
			}
			return canvas.toDataURL( 'image/jpeg', 0.9 );
		},
		[ width, height ]
	);

	return {
		name: fileName,
		mimeType: 'image/jpeg',
		buffer: Buffer.from( dataUrl.split( ',' )[ 1 ], 'base64' ),
	};
}

/**
 * Uploads an image from an Image block and returns the attachment once the browser finalized the upload.
 *
 * @param {Object}       fixtures              Playwright fixtures.
 * @param {Admin}        fixtures.admin        Admin utils.
 * @param {Editor}       fixtures.editor       Editor utils.
 * @param {Page}         fixtures.page         Playwright page.
 * @param {RequestUtils} fixtures.requestUtils Request utils.
 * @param {Upload}       image                 The image to upload.
 * @return {Promise<Attachment>} The attachment in the edit context.
 */
async function uploadFromImageBlock(
	{ admin, editor, page, requestUtils },
	image
) {
	await admin.createNewPost();

	// The test is only meaningful when the browser is the one processing the images.
	expect(
		await page.evaluate(
			() =>
				// @ts-ignore -- Set by WordPress core.
				window.__clientSideMediaProcessing === true &&
				window.crossOriginIsolated
		),
		'Client side media processing should be active in the editor'
	).toBe( true );

	await editor.insertBlock( { name: 'core/image' } );
	const imageBlock = editor.canvas.locator(
		'role=document[name="Block: Image"i]'
	);

	const finalized = page.waitForResponse(
		( response ) =>
			/\/wp\/v2\/media\/\d+\/finalize/.test(
				decodeURIComponent( response.url() )
			),
		{ timeout: 120000 }
	);
	await imageBlock
		.locator( 'data-testid=form-file-upload-input' )
		.setInputFiles( image );
	expect( ( await finalized ).ok() ).toBe( true );

	const [ block ] = await editor.getBlocks();

	return requestUtils.rest( {
		path: `/wp/v2/media/${ block.attributes.id }`,
		params: { context: 'edit' },
	} );
}

/**
 * Asserts that the published image offers AVIF with a fallback which is free of AVIF.
 *
 * @param {Editor} editor   Editor utils.
 * @param {Page}   page     Playwright page.
 * @param {string} mimeType The mime type to fall back to.
 */
async function expectPictureWithFallback( editor, page, mimeType ) {
	const postId = await editor.publishPost();
	await page.goto( `/?p=${ postId }` );

	const picture = page.locator( '.wp-block-image picture' );
	await expect(
		picture.locator( 'source[type="image/avif"]' )
	).toHaveAttribute( 'srcset', /\.avif/ );

	const extension = 'image/png' === mimeType ? /\.png$/ : /\.jpe?g$/;
	const img = picture.locator( 'img' );
	await expect( img ).toHaveAttribute( 'src', extension );

	const srcset = ( await img.getAttribute( 'srcset' ) ) ?? '';
	for ( const candidate of srcset.split( ', ' ).filter( Boolean ) ) {
		expect( candidate.split( ' ' )[ 0 ] ).toMatch( extension );
	}
}

/**
 * Gets the sub-sizes of an attachment, leaving out the full size which the REST API lists along with them.
 *
 * @param {Attachment} attachment The attachment.
 * @return {Array<[string, Size]>} The sub-sizes by name.
 */
function getSubSizes( attachment ) {
	return Object.entries( attachment.media_details.sizes ).filter(
		( [ name ] ) => 'full' !== name
	);
}

/**
 * Asserts that an image file is served in the expected format.
 *
 * @param {RequestUtils} requestUtils Request utils.
 * @param {string}       url          URL of the image.
 * @param {string}       mimeType     The expected mime type.
 */
async function expectImage( requestUtils, url, mimeType ) {
	const response = await requestUtils.request.get( url );
	expect( response.status(), url ).toBe( 200 );
	expect( response.headers()[ 'content-type' ], url ).toBe( mimeType );
}

test.describe( 'client side media processing', () => {
	// The browser encodes every sub-size, which takes a while for AVIF.
	test.setTimeout( 180000 );

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'modern-image-formats' );
		await requestUtils.deleteAllMedia();
	} );

	test.afterEach( async ( { requestUtils } ) => {
		await requestUtils.deleteAllMedia();
	} );

	test.describe( 'with fallback images', () => {
		test.beforeEach( async ( { admin, page } ) => {
			await saveSettings( admin, page, true );
		} );

		[
			{ fileName: 'drawing.jpg', mimeType: 'image/jpeg' },
			{ fileName: 'dice.png', mimeType: 'image/png' },
		].forEach( ( { fileName, mimeType } ) => {
			test( `should keep the ${ mimeType } fallback of every sub-size`, async ( {
				admin,
				editor,
				page,
				requestUtils,
			} ) => {
				const attachment = await uploadFromImageBlock(
					{ admin, editor, page, requestUtils },
					'image/png' === mimeType
						? await readImage( fileName, mimeType )
						: await drawImage( page, fileName, 1200, 800 )
				);
				const baseUrl = path.dirname( attachment.source_url );

				// The uploaded image stays the attached file, as it does for a server side upload.
				expect( attachment.source_url ).toMatch(
					new RegExp( `\\${ path.extname( fileName ) }$` )
				);
				expect(
					attachment.media_details.original_image
				).toBeUndefined();
				await expectImage(
					requestUtils,
					attachment.source_url,
					mimeType
				);

				const sizes = getSubSizes( attachment );
				expect( sizes.length ).toBeGreaterThan( 0 );

				// The browser generated AVIF is kept wherever it is the smaller file.
				expect(
					sizes.filter(
						( [ , size ] ) => 'image/avif' === size.mime_type
					).length
				).toBeGreaterThan( 0 );

				for ( const [ name, size ] of sizes ) {
					expect(
						size.sources,
						`The ${ name } size should have a fallback`
					).toHaveProperty( [ mimeType ] );

					for ( const [ sourceMimeType, source ] of Object.entries(
						size.sources ?? {}
					) ) {
						await expectImage(
							requestUtils,
							`${ baseUrl }/${ source.file }`,
							sourceMimeType
						);
					}
				}

				await expectPictureWithFallback( editor, page, mimeType );
			} );
		} );

		test( 'should keep the fallbacks of an image over the big image size threshold', async ( {
			admin,
			editor,
			page,
			requestUtils,
		} ) => {
			const attachment = await uploadFromImageBlock(
				{ admin, editor, page, requestUtils },
				await drawImage( page, 'big-image.jpg', 3000, 2000 )
			);
			const baseUrl = path.dirname( attachment.source_url );

			// The browser supplies the scaled full size as AVIF, the upload is kept as the original image.
			expect( attachment.source_url ).toMatch( /-scaled\.avif$/ );
			expect( attachment.media_details.original_image ).toMatch(
				/\.jpg$/
			);
			// The full size falls back to the upload itself.
			const fullSources = attachment.media_details.sizes.full.sources;
			expect( fullSources?.[ 'image/avif' ]?.file ).toBe(
				path.basename( attachment.source_url )
			);
			expect( fullSources?.[ 'image/jpeg' ]?.file ).toBe(
				attachment.media_details.original_image
			);

			const sizes = getSubSizes( attachment );
			expect( sizes.length ).toBeGreaterThan( 0 );

			for ( const [ name, size ] of sizes ) {
				expect(
					size.sources,
					`The ${ name } size should have a fallback`
				).toHaveProperty( [ 'image/jpeg' ] );
				await expectImage(
					requestUtils,
					`${ baseUrl }/${ size.sources?.[ 'image/jpeg' ].file }`,
					'image/jpeg'
				);
			}

			await expectPictureWithFallback( editor, page, 'image/jpeg' );
		} );
	} );

	test.describe( 'without fallback images', () => {
		test.beforeEach( async ( { admin, page } ) => {
			await saveSettings( admin, page, false );
		} );

		test( 'should only store AVIF images', async ( {
			admin,
			editor,
			page,
			requestUtils,
		} ) => {
			const attachment = await uploadFromImageBlock(
				{ admin, editor, page, requestUtils },
				await readImage( 'leaves.jpg', 'image/jpeg' )
			);

			// The server converts the upload itself when there is no fallback to keep.
			expect( attachment.source_url ).toMatch( /\.avif$/ );
			await expectImage(
				requestUtils,
				attachment.source_url,
				'image/avif'
			);

			const sizes = getSubSizes( attachment );
			expect( sizes.length ).toBeGreaterThan( 0 );

			for ( const [ name, size ] of sizes ) {
				expect( size.mime_type, name ).toBe( 'image/avif' );
				expect( Object.keys( size.sources ?? {} ), name ).toEqual( [
					'image/avif',
				] );
				await expectImage(
					requestUtils,
					size.source_url,
					'image/avif'
				);
			}
		} );
	} );
} );
