<?php
/**
 * Link prediction functions used for Speculative Loading.
 *
 * @package speculation-rules
 * @since n.e.x.t
 */

declare( strict_types = 1 );

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Determines whether link prediction is enabled.
 *
 * @since n.e.x.t
 *
 * @return bool Whether enabled.
 */
function plsr_is_link_prediction_enabled(): bool {
	if ( ! plsr_is_speculative_loading_enabled() ) {
		return false;
	}

	$option = plsr_get_stored_setting_value();
	return 'enabled' === $option['prediction'];
}

/**
 * Gets the path to a script.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $src_path Source path, relative to the plugin root.
 * @return string Full path to the minified script, or to the source if `SCRIPT_DEBUG` is on or the script is not built.
 *
 * @noinspection PhpDocMissingThrowsInspection
 */
function plsr_get_asset_path( string $src_path ): string {
	$min_path   = (string) preg_replace( '/(?=\.\w+$)/', '.min', $src_path );
	$plugin_dir = trailingslashit( __DIR__ );

	$force_src = false;
	if ( WP_DEBUG && ! file_exists( $plugin_dir . $min_path ) ) {
		$force_src = true;
		/**
		 * No WP_Exception is thrown by wp_trigger_error() since E_USER_ERROR is not passed as the error level.
		 *
		 * @noinspection PhpUnhandledExceptionInspection
		 */
		wp_trigger_error(
			__FUNCTION__,
			sprintf(
				/* translators: %s is the minified asset path */
				__( 'Minified asset has not been built: %s', 'speculation-rules' ),
				$min_path
			),
			E_USER_WARNING
		);
	}

	if ( SCRIPT_DEBUG || $force_src ) {
		return $plugin_dir . $src_path;
	}

	return $plugin_dir . $min_path;
}

/**
 * Gets the mode and conditions of the document rule that speculative loading uses on the current page.
 *
 * A predicted link is only loaded if it also meets these conditions, so the exclusions that apply to speculative
 * loading in general (admin URLs, `nofollow` links, filtered paths and so on) apply to predicted links too.
 *
 * @since n.e.x.t
 * @access private
 *
 * @return array{ mode: 'prefetch'|'prerender', where: array<string, mixed> }|null Mode and conditions, or null if there is no document rule.
 */
function plsr_get_link_prediction_rule(): ?array {
	if ( function_exists( 'wp_get_speculation_rules' ) ) {
		// There is no public function for reading the rules, and the document rule conditions are needed as is.
		$speculation_rules = wp_get_speculation_rules(); // @phpstan-ignore no.private.function
		$rules_by_mode     = null !== $speculation_rules ? $speculation_rules->jsonSerialize() : null;
	} else {
		$rules_by_mode = plsr_get_speculation_rules();
	}
	if ( ! is_array( $rules_by_mode ) ) {
		return null;
	}

	foreach ( array( 'prerender', 'prefetch' ) as $mode ) {
		if ( ! isset( $rules_by_mode[ $mode ] ) || ! is_array( $rules_by_mode[ $mode ] ) ) {
			continue;
		}
		foreach ( $rules_by_mode[ $mode ] as $rule ) {
			if ( is_array( $rule ) && isset( $rule['where'] ) && is_array( $rule['where'] ) && count( $rule['where'] ) > 0 ) {
				/**
				 * Conditions.
				 *
				 * @var array<string, mixed> $where
				 */
				$where = $rule['where'];
				return array(
					'mode'  => $mode,
					'where' => $where,
				);
			}
		}
	}

	return null;
}

/**
 * Gets the configuration for the link prediction script.
 *
 * @since n.e.x.t
 *
 * @return array{ mode: 'prefetch'|'prerender', where: array<string, mixed>, threshold: float, maxLinks: int, learningRate: float, weights: array<string, float> }|null Configuration, or null if no links can be speculatively loaded on the current page.
 */
function plsr_get_link_prediction_config(): ?array {
	$rule = plsr_get_link_prediction_rule();
	if ( null === $rule ) {
		return null;
	}

	$default_args = array(
		'threshold'     => 0.5,
		'max_links'     => 2,
		'learning_rate' => 0.1,
		'weights'       => array(
			'bias'       => -3.0,
			'visible'    => 1.0,
			'proximity'  => 0.5,
			'area'       => 1.5,
			'content'    => 0.8,
			'navigation' => 0.4,
			'footer'     => -1.0,
			'heading'    => 0.8,
			'image'      => 0.3,
			'next'       => 1.5,
			'repeated'   => 0.6,
		),
	);

	/**
	 * Filters the arguments for link prediction.
	 *
	 * The probability that a visitor follows a link is the logistic function of the weighted sum of the link's
	 * signals, each of which is a number from 0 to 1.
	 *
	 * @since n.e.x.t
	 *
	 * @param array<string, mixed> $args {
	 *     Link prediction arguments.
	 *
	 *     @type float                $threshold     Probability from 0 to 1 that a link needs to reach to be speculatively loaded. Default 0.5.
	 *     @type int                  $max_links     Maximum number of predicted links to speculatively load per page view. Default 2.
	 *     @type float                $learning_rate How strongly the weights adapt to the links a visitor follows. The adapted weights are
	 *                                               stored in the visitor's browser. Use 0 to disable. Default 0.1.
	 *     @type array<string, float> $weights {
	 *         Weight of each signal.
	 *
	 *         @type float $bias       Starting point, before any signal is counted. Default -3.
	 *         @type float $visible    The link is in the viewport. Default 1.
	 *         @type float $proximity  How close the link is to the middle of the viewport. Default 0.5.
	 *         @type float $area       How large the clickable area of the link is. Default 1.5.
	 *         @type float $content    The link is inside the main content. Default 0.8.
	 *         @type float $navigation The link is inside a header or navigation menu. Default 0.4.
	 *         @type float $footer     The link is inside a footer or sidebar. Default -1.
	 *         @type float $heading    The link is inside a heading. Default 0.8.
	 *         @type float $image      The link contains an image. Default 0.3.
	 *         @type float $next       The link goes to the next page or post. Default 1.5.
	 *         @type float $repeated   Other links on the page go to the same URL. Default 0.6.
	 *     }
	 * }
	 * @param string               $mode Mode used to apply speculative loading. Either 'prefetch' or 'prerender'.
	 */
	$args = apply_filters( 'plsr_link_prediction_args', $default_args, $rule['mode'] );
	if ( ! is_array( $args ) ) {
		$args = $default_args;
	}

	$weights = $default_args['weights'];
	if ( isset( $args['weights'] ) && is_array( $args['weights'] ) ) {
		foreach ( array_keys( $weights ) as $signal ) {
			if ( isset( $args['weights'][ $signal ] ) && is_numeric( $args['weights'][ $signal ] ) ) {
				$weights[ $signal ] = (float) $args['weights'][ $signal ];
			}
		}
	}

	$threshold     = isset( $args['threshold'] ) && is_numeric( $args['threshold'] ) ? (float) $args['threshold'] : $default_args['threshold'];
	$max_links     = isset( $args['max_links'] ) && is_numeric( $args['max_links'] ) ? (int) $args['max_links'] : $default_args['max_links'];
	$learning_rate = isset( $args['learning_rate'] ) && is_numeric( $args['learning_rate'] ) ? (float) $args['learning_rate'] : $default_args['learning_rate'];

	return array(
		'mode'         => $rule['mode'],
		'where'        => $rule['where'],
		'threshold'    => min( 1.0, max( 0.0, $threshold ) ),
		'maxLinks'     => max( 0, $max_links ),
		'learningRate' => min( 1.0, max( 0.0, $learning_rate ) ),
		'weights'      => $weights,
	);
}

/**
 * Prints the link prediction script and its configuration.
 *
 * See {@see 'wp_footer'}.
 *
 * @since n.e.x.t
 * @access private
 */
function plsr_print_link_prediction_script(): void {
	if ( ! plsr_is_link_prediction_enabled() ) {
		return;
	}

	$config = plsr_get_link_prediction_config();
	if ( null === $config || 0 === $config['maxLinks'] ) {
		return;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$script = file_get_contents( plsr_get_asset_path( 'predict.js' ) );
	if ( false === $script || '' === $script ) {
		// This clause should never be entered, but is needed to please PHPStan. Can't hurt to be safe.
		return; // @codeCoverageIgnore
	}

	wp_print_inline_script_tag(
		(string) wp_json_encode( $config, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES ),
		array(
			'type' => 'application/json',
			'id'   => 'plsr-link-prediction-config',
		)
	);

	// An inline script is used to avoid an extra request.
	wp_print_inline_script_tag(
		$script . "\n//# sourceURL=speculation-rules-link-prediction",
		array( 'type' => 'module' )
	);
}
add_action( 'wp_footer', 'plsr_print_link_prediction_script', 11 );
