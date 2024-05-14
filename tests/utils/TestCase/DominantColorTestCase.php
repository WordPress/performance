<?php

namespace PerformanceLab\Tests\TestCase;

use WP_UnitTestCase;

abstract class DominantColorTestCase extends WP_UnitTestCase {
	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array<string, mixed>
	 */
	public function provider_get_dominant_color(): array {
		return array(
			'animated_gif'  => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/animated.gif',
				'expected_color'        => array( '874e4e', '864e4e', 'df7f7f' ),
				'expected_transparency' => true,
			),
			'red_jpg'       => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/red.jpg',
				'expected_color'        => array( 'ff0000', 'fe0000' ),
				'expected_transparency' => false,
			),
			'green_jpg'     => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/green.jpg',
				'expected_color'        => array( '00ff00', '00ff01', '02ff01' ),
				'expected_transparency' => false,
			),
			'white_jpg'     => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/white.jpg',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),

			'red_gif'       => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/red.gif',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
			),
			'green_gif'     => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/green.gif',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
			),
			'white_gif'     => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/white.gif',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),
			'trans_gif'     => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/trans.gif',
				'expected_color'        => array( '5a5a5a', '020202' ),
				'expected_transparency' => true,
			),

			'red_png'       => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/red.png',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
			),
			'green_png'     => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/green.png',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
			),
			'white_png'     => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/white.png',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),
			'trans_png'     => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/trans.png',
				'expected_color'        => array( '000000' ),
				'expected_transparency' => true,
			),

			'red_webp'      => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/red.webp',
				'expected_color'        => array( 'ff0000' ),
				'expected_transparency' => false,
			),
			'green_webp'    => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/green.webp',
				'expected_color'        => array( '00ff00' ),
				'expected_transparency' => false,
			),
			'white_webp'    => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/white.webp',
				'expected_color'        => array( 'ffffff' ),
				'expected_transparency' => false,
			),
			'trans_webp'    => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/trans.webp',
				'expected_color'        => array( '000000' ),
				'expected_transparency' => true,
			),
			'balloons_webp' => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp',
				'expected_color'        => array( 'c1bbb9', 'c0bbb9', 'c0bab8', 'c3bdbd', 'bfbab8' ),
				'expected_transparency' => false,
			),
		);
	}

	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array<string, mixed>
	 */
	public function provider_get_dominant_color_invalid_images(): array {
		return array(
			'tiff' => array(
				'image_path' => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/test-image.tiff',
			),
			'bmp'  => array(
				'image_path' => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/test-image.bmp',
			),
		);
	}

	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array<string, mixed>
	 */
	public function provider_get_dominant_color_none_images(): array {
		return array(
			'pdf' => array(
				'files_path' => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/wordpress-gsoc-flyer.pdf',
			),
			'mp4' => array(
				'files_path' => TESTS_PLUGIN_DIR . '/tests/plugins/dominant-color-images/data/images/small-video.mp4',
			),
		);
	}
}
