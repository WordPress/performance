<?php
/**
 * LQIP Generator
 *
 * Generates a CSS-only Low Quality Image Placeholder integer from an
 * RGB base colour and a 3×2 grid of sampled pixel values.
 *
 * The integer encodes:
 *  - 6 relative-lightness values (2 bits each) for a 3×2 cell grid
 *  - A base OKLab color (2-bit L, 3-bit a, 3-bit b)
 *
 * The encoded integer is valid in the range [-999999, 999999] for use
 * as a CSS custom property.
 *
 * Algorithm ported from https://github.com/nicktacular/css-only-lqip-generator
 *
 * @package dominant-color-images
 * @since 1.3.0
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Generate an LQIP integer from an RGB colour and 6 grid pixels.
 *
 * The grid pixels are in left-to-right, top-to-bottom order.
 * The RGB values are converted to OKLab internally.
 *
 * @param array{r: int, g: int, b: int}             $rgb     Base / dominant colour as ['r' => R, 'g' => G, 'b' => B] (0-255).
 * @param array<int, array{r: int, g: int, b: int}> $grid_rgb 6 grid cell colours as ['r'=>R, 'g'=>G, 'b'=>B].
 *
 * @return int LQIP integer in the range [-524288, 524287].
 */
function dominant_color_lqip_generate( array $rgb, array $grid_rgb ): int {
	$oklab    = dominant_color_lqip_rgb_to_oklab( $rgb['r'], $rgb['g'], $rgb['b'] );
	$base_lab = array(
		'L' => $oklab['L'],
		'a' => $oklab['a'],
		'b' => $oklab['b'],
	);
	$bits     = dominant_color_lqip_find_oklab_bits( $base_lab['L'], $base_lab['a'], $base_lab['b'] );
	$ll       = $bits['ll'];
	$aaa      = $bits['aaa'];
	$bbb      = $bits['bbb'];

	$base = dominant_color_lqip_bits_to_lab( $ll, $aaa, $bbb );

	// Compute relative lightness for each grid cell.
	$values = array();
	foreach ( $grid_rgb as $cell_rgb ) {
		$cell_lab = dominant_color_lqip_rgb_to_oklab( $cell_rgb['r'], $cell_rgb['g'], $cell_rgb['b'] );
		$values[] = max( 0.0, min( 1.0, 0.5 + $cell_lab['L'] - $base['L'] ) );
	}

	// Pack 6 × 2-bit cell values + 8-bit base colour.
	$ca = (int) round( $values[0] * 3 );
	$cb = (int) round( $values[1] * 3 );
	$cc = (int) round( $values[2] * 3 );
	$cd = (int) round( $values[3] * 3 );
	$ce = (int) round( $values[4] * 3 );
	$cf = (int) round( $values[5] * 3 );

	$lqip =
		-( 2 ** 19 ) +
		( ( $ca & 3 ) << 18 ) +
		( ( $cb & 3 ) << 16 ) +
		( ( $cc & 3 ) << 14 ) +
		( ( $cd & 3 ) << 12 ) +
		( ( $ce & 3 ) << 10 ) +
		( ( $cf & 3 ) << 8 ) +
		( ( $ll & 3 ) << 6 ) +
		( ( $aaa & 7 ) << 3 ) +
		( $bbb & 7 );

	return $lqip;
}

/**
 * Convert an sRGB pixel [0-255] to OKLab.
 *
 * Applies proper sRGB gamma linearisation before the colour-space
 * conversion.
 *
 * @param int $r Red channel [0-255].
 * @param int $g Green channel [0-255].
 * @param int $b Blue channel [0-255].
 * @return array{L: float, a: float, b: float}
 */
function dominant_color_lqip_rgb_to_oklab( int $r, int $g, int $b ): array {
	$r_norm = $r / 255.0;
	$g_norm = $g / 255.0;
	$b_norm = $b / 255.0;

	// sRGB gamma decoding (linearise): IEC 61966-2-1.
	$r_lin = $r_norm >= 0.04045
		? ( ( $r_norm + 0.055 ) / 1.055 ) ** 2.4
		: $r_norm / 12.92;
	$g_lin = $g_norm >= 0.04045
		? ( ( $g_norm + 0.055 ) / 1.055 ) ** 2.4
		: $g_norm / 12.92;
	$b_lin = $b_norm >= 0.04045
		? ( ( $b_norm + 0.055 ) / 1.055 ) ** 2.4
		: $b_norm / 12.92;

	// Linear RGB → LMS (cube-root in one step).
	$l = ( 0.4122214708 * $r_lin + 0.5363325363 * $g_lin + 0.0514459929 * $b_lin ) ** ( 1.0 / 3.0 );
	$m = ( 0.2119034982 * $r_lin + 0.6806995451 * $g_lin + 0.1073969566 * $b_lin ) ** ( 1.0 / 3.0 );
	$s = ( 0.0883024619 * $r_lin + 0.2817188376 * $g_lin + 0.6299787005 * $b_lin ) ** ( 1.0 / 3.0 );

	return array(
		'L' => $l * 0.2104542553 + $m * 0.793617785 + $s * ( -0.0040720468 ),
		'a' => $l * 1.9779984951 + $m * ( -2.428592205 ) + $s * 0.4505937099,
		'b' => $l * 0.0259040371 + $m * 0.7827717662 + $s * ( -0.808675766 ),
	);
}

/**
 * Decode packed bits back to OKLab values.
 *
 * @param int $ll  2-bit lightness index [0-3].
 * @param int $aaa 3-bit a-axis index [0-7].
 * @param int $bbb 3-bit b-axis index [0-7].
 * @return array{L: float, a: float, b: float}
 */
function dominant_color_lqip_bits_to_lab( int $ll, int $aaa, int $bbb ): array {
	return array(
		'L' => ( $ll / 3 ) * 0.6 + 0.2,
		'a' => ( $aaa / 8 ) * 0.7 - 0.35,
		'b' => ( ( $bbb + 1 ) / 8 ) * 0.7 - 0.35,
	);
}

/**
 * Scale a chroma component for perceptual difference calculation.
 *
 * @param float $x      The component value.
 * @param float $chroma The chroma (sqrt(a²+b²)) of the colour.
 */
function dominant_color_lqip_scale_component_for_diff( float $x, float $chroma ): float {
	return $x / ( 1e-6 + $chroma ** 0.5 );
}

/**
 * Brute-force search for the 8-bit (2+3+3) combination that best
 * approximates the given OKLab target colour.
 *
 * @param float $target_l Target L channel.
 * @param float $target_a Target a channel.
 * @param float $target_b Target b channel.
 * @return array{ll: int, aaa: int, bbb: int}
 */
function dominant_color_lqip_find_oklab_bits( float $target_l, float $target_a, float $target_b ): array {
	$target_chroma       = hypot( $target_a, $target_b );
	$scaled_target_a     = dominant_color_lqip_scale_component_for_diff( $target_a, $target_chroma );
		$scaled_target_b = dominant_color_lqip_scale_component_for_diff( $target_b, $target_chroma );

	$best_bits = array( 0, 0, 0 );
	$best_diff = INF;

	for ( $ll = 0; $ll <= 3; $ll++ ) {
		for ( $aaa = 0; $aaa <= 7; $aaa++ ) {
			for ( $bbb = 0; $bbb <= 7; $bbb++ ) {
				$lab    = dominant_color_lqip_bits_to_lab( $ll, $aaa, $bbb );
				$chroma = hypot( $lab['a'], $lab['b'] );

				$gray_penalty = ( 4 === $aaa && 3 === $bbb ) ? 0.04 : 0.0;

				$scaled_a     = dominant_color_lqip_scale_component_for_diff( $lab['a'], $chroma );
					$scaled_b = dominant_color_lqip_scale_component_for_diff( $lab['b'], $chroma );

				$diff = $gray_penalty + sqrt(
					( $lab['L'] - $target_l ) ** 2 +
					( $scaled_a - $scaled_target_a ) ** 2 +
					( $scaled_b - $scaled_target_b ) ** 2
				);

				if ( $diff < $best_diff ) {
					$best_diff = $diff;
					$best_bits = array( $ll, $aaa, $bbb );
				}
			}
		}
	}

	return array(
		'll'  => $best_bits[0],
		'aaa' => $best_bits[1],
		'bbb' => $best_bits[2],
	);
}
