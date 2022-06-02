<?php

namespace PerformanceLab\Tests\TestCase;

use WP_UnitTestCase;

abstract class DominantColorTestCase extends WP_UnitTestCase {
	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array
	 */
	public function provider_get_dominant_color() {
		return array(

			'red_jpg'     => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/red.jpg',
				'expected_color' => array( 'ff56', 'ff0505', 'ff0506' ),
				'is_wp_error'    => false,
			),
			'green_jpg'   => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/green.jpg',
				'expected_color' => array( '1ee204', '1ee24' ),
				'is_wp_error'    => false,
			),
			'white_jpg'   => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/white.jpg',
				'expected_color' => array( 'ffffff' ),
				'is_wp_error'    => false,
			),
			'trans_jpg'   => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans.jpg',
				'expected_color' => array( 'ffffff' ),
				'is_wp_error'    => false,
			),
			'trans4_jpg'  => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans4.jpg',
				'expected_color' => array( 'd3febf', 'd2febf' ),
				'is_wp_error'    => false,
			),

			'red_gif'     => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/red.gif',
				'expected_color' => array( 'ff0505', 'ff55', 'ff0506' ),
				'is_wp_error'    => false,
			),
			'green_gif'   => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/green.gif',
				'expected_color' => array( '1ee204', '1ee24' ),
				'is_wp_error'    => false,
			),
			'white_gif'   => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/white.gif',
				'expected_color' => array( 'ffffff' ),
				'is_wp_error'    => false,
			),
			'trans_gif'   => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans.gif',
				'expected_color' => array( 'ffffff' ),
				'is_wp_error'    => false,
			),
			'trans4_gif'  => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans4.gif',
				'expected_color' => array( '133f00', '4cfc0' ),
				'is_wp_error'    => false,
			),

			'red_png'     => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/red.png',
				'expected_color' => array( 'ff0505', 'ff55', 'ff0506', 'ff56' ),
				'is_wp_error'    => false,
			),
			'green_png'   => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/green.png',
				'expected_color' => array( '1ee204', '1ee24' ),
				'is_wp_error'    => false,
			),
			'white_png'   => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/white.png',
				'expected_color' => array( 'ffffff' ),
				'is_wp_error'    => false,
			),
			'trans_png'   => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans.png',
				'expected_color' => array( '000' ),
				'is_wp_error'    => true,
			),
			'trans4_png'  => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans4.png',
				'expected_color' => array( '4cfc0' ),
				'is_wp_error'    => false,
			),

			'red_webp'    => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/red.webp',
				'expected_color' => array( 'ff0505', 'ff55', 'ff0506', 'ff56' ),
				'is_wp_error'    => false,
			),
			'green_webp'  => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/green.webp',
				'expected_color' => array( '1de303', '1de33' ),
				'is_wp_error'    => false,
			),
			'white_webp'  => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/white.webp',
				'expected_color' => array( 'ffffff' ),
				'is_wp_error'    => false,
			),
			'trans_webp'  => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans.webp',
				'expected_color' => array( '000' ),
				'is_wp_error'    => true,
			),
			'trans4_webp' => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans4.webp',
				'expected_color' => array( '4dfc0' ),
				'is_wp_error'    => false,
			),
			'gif'         => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/earth.gif',
				'expected_color' => array( '151517', '1b1a1c' ),
				'is_wp_error'    => false,
			),
			'webp'        => array(
				'image_path'     => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/balloons.webp',
				'expected_color' => array( 'c5bec0', 'c1bbb9', 'c3bdbd' ),
				'is_wp_error'    => false,
			),
		);
	}

	/**
	 * Data provider for test_get_dominant_color_GD.
	 *
	 * @return array
	 */
	public function provider_get_has_transparency() {
		return array(
			'white_jpg'   => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/white.jpg',
				'expected_transparency' => false,
			),
			'trans_jpg'   => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans.jpg',
				'expected_transparency' => false,
			),
			'trans4_jpg'  => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans4.jpg',
				'expected_transparency' => false,
			),

			'white_gif'   => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/white.gif',
				'expected_transparency' => true,
			),
			'trans_gif'   => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans.gif',
				'expected_transparency' => true,
			),
			'trans4_gif'  => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans4.gif',
				'expected_transparency' => true,
			),

			'white_png'   => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/white.png',
				'expected_transparency' => false,
			),
			'trans_png'   => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans.png',
				'expected_transparency' => true,
			),
			'trans4_png'  => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans4.png',
				'expected_transparency' => true,
			),

			'white_webp'  => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/white.webp',
				'expected_transparency' => false,
			),
			'trans_webp'  => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans.webp',
				'expected_transparency' => true,
			),
			'trans4_webp' => array(
				'image_path'            => TESTS_PLUGIN_DIR . '/tests/testdata/modules/images/dominant-color/trans4.webp',
				'expected_transparency' => true,
			),
		);
	}
}
