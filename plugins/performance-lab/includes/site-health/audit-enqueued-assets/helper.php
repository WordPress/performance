<?php
/**
 * Helper functions used for Enqueued Assets Health Check.
 *
 * @package performance-lab
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Callback for enqueued_js_assets test.
 *
 * @since 1.0.0
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}|array{omitted: true} Result.
 */
function perflab_aea_enqueued_js_assets_test(): array {
	/**
	 * If the test didn't run yet, deactivate.
	 */
	$enqueued_scripts = perflab_aea_get_total_enqueued_scripts();
	$bytes_enqueued   = perflab_aea_get_total_size_bytes_enqueued_scripts();
	if ( false === $enqueued_scripts || false === $bytes_enqueued ) {
		// The return value is validated in JavaScript at:
		// <https://github.com/WordPress/wordpress-develop/blob/d1e0a6241dcc34f4a5ed464a741116461a88d43b/src/js/_enqueues/admin/site-health.js#L65-L114>
		// If the value lacks the required keys of test, label, and description then it is omitted.
		return array( 'omitted' => true );
	}

	$result = array(
		'label'       => __( 'Blocking scripts', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'The amount of %1$s blocking script (size: %2$s) is acceptable.',
						'The amount of %1$s blocking scripts (size: %2$s) is acceptable.',
						$enqueued_scripts,
						'performance-lab'
					),
					$enqueued_scripts,
					size_format( $bytes_enqueued )
				)
			)
		),
		'actions'     => '',
		'test'        => 'enqueued_js_assets',
	);

	/**
	 * Filters number of enqueued scripts to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $scripts_threshold Scripts threshold number. Default 30.
	 */
	$scripts_threshold = apply_filters( 'perflab_aea_enqueued_scripts_threshold', 30 );

	/**
	 * Filters size of enqueued scripts to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $scripts_size_threshold Enqueued Scripts size (in bytes) threshold. Default 300000.
	 */
	$scripts_size_threshold = apply_filters( 'perflab_aea_enqueued_scripts_byte_size_threshold', 300000 );

	if ( $enqueued_scripts > $scripts_threshold || $bytes_enqueued > $scripts_size_threshold ) {
		$result['status'] = 'recommended';

		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'Your website has %1$s blocking script (size: %2$s). Try to reduce the number or to concatenate them.',
						'Your website has %1$s blocking scripts (size: %2$s). Try to reduce the number or to concatenate them.',
						$enqueued_scripts,
						'performance-lab'
					),
					$enqueued_scripts,
					size_format( $bytes_enqueued )
				)
			)
		);

		$result['actions'] = sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. 3.URL to clean cache. 4. Clean Cache text. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			__( 'More info about performance optimization', 'performance-lab' ),
			esc_url( add_query_arg( 'action', 'clean_aea_audit', wp_nonce_url( admin_url( 'site-health.php' ), 'clean_aea_audit' ) ) ),
			__( 'Clean Test Cache', 'performance-lab' )
		);
	}

	return $result;
}

/**
 * Callback for enqueued_css_assets test.
 *
 * @since 1.0.0
 *
 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}|array{omitted: true} Result.
 */
function perflab_aea_enqueued_css_assets_test(): array {
	// Omit if the test didn't run yet, omit.
	$enqueued_styles = perflab_aea_get_total_enqueued_styles();
	$bytes_enqueued  = perflab_aea_get_total_size_bytes_enqueued_styles();
	if ( false === $enqueued_styles || false === $bytes_enqueued ) {
		// The return value is validated in JavaScript at:
		// <https://github.com/WordPress/wordpress-develop/blob/d1e0a6241dcc34f4a5ed464a741116461a88d43b/src/js/_enqueues/admin/site-health.js#L65-L114>
		// If the value lacks the required keys of test, label, and description then it is omitted.
		return array( 'omitted' => true );
	}
	$result = array(
		'label'       => __( 'Blocking styles', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'The amount of %1$s blocking style (size: %2$s) is acceptable.',
						'The amount of %1$s blocking styles (size: %2$s) is acceptable.',
						$enqueued_styles,
						'performance-lab'
					),
					$enqueued_styles,
					size_format( $bytes_enqueued )
				)
			)
		),
		'actions'     => '',
		'test'        => 'enqueued_css_assets',
	);

	/**
	 * Filters number of enqueued styles to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $styles_threshold Styles threshold number. Default 10.
	 */
	$styles_threshold = apply_filters( 'perflab_aea_enqueued_styles_threshold', 10 );

	/**
	 * Filters size of enqueued styles to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $styles_size_threshold Enqueued styles size (in bytes) threshold. Default 100000.
	 */
	$styles_size_threshold = apply_filters( 'perflab_aea_enqueued_styles_byte_size_threshold', 100000 );
	if ( $enqueued_styles > $styles_threshold || perflab_aea_get_total_size_bytes_enqueued_styles() > $styles_size_threshold ) {
		$result['status'] = 'recommended';

		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'Your website has %1$s blocking style (size: %2$s). Try to reduce the number or to concatenate them.',
						'Your website has %1$s blocking styles (size: %2$s). Try to reduce the number or to concatenate them.',
						$enqueued_styles,
						'performance-lab'
					),
					$enqueued_styles,
					size_format( $bytes_enqueued )
				)
			)
		);

		$result['actions'] = sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. 3.URL to clean cache. 4. Clean Cache text. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			__( 'More info about performance optimization', 'performance-lab' ),
			esc_url( add_query_arg( 'action', 'clean_aea_audit', wp_nonce_url( admin_url( 'site-health.php' ), 'clean_aea_audit' ) ) ),
			__( 'Clean Test Cache', 'performance-lab' )
		);
	}

	return $result;
}

/**
 * Gets total of enqueued scripts.
 *
 * @since 1.0.0
 *
 * @return int|false Number of total scripts or false if transient hasn't been set.
 */
function perflab_aea_get_total_enqueued_scripts() {
	$enqueued_scripts = false;
	$blocking_assets  = get_transient( 'aea_blocking_assets' );
	if ( is_array( $blocking_assets ) && is_array( $blocking_assets['scripts'] ) ) {
		$enqueued_scripts = count( $blocking_assets['scripts'] );
	}
	return $enqueued_scripts;
}

/**
 * Gets total size in bytes of Enqueued Scripts.
 *
 * @since 1.0.0
 *
 * @return int|false Byte Total size or false if transient hasn't been set.
 */
function perflab_aea_get_total_size_bytes_enqueued_scripts() {
	$total_size      = false;
	$blocking_assets = get_transient( 'aea_blocking_assets' );
	if ( is_array( $blocking_assets ) && isset( $blocking_assets['scripts'] ) && is_array( $blocking_assets['scripts'] ) ) {
		$total_size = 0;
		foreach ( $blocking_assets['scripts'] as $enqueued_script ) {
			if ( is_array( $enqueued_script ) && array_key_exists( 'size', $enqueued_script ) && is_int( $enqueued_script['size'] ) ) {
				$total_size += $enqueued_script['size'];
			}
		}
	}
	return $total_size;
}

/**
 * Gets total of enqueued styles.
 *
 * @since 1.0.0
 *
 * @return int|false Number of total styles or false if transient hasn't been set.
 */
function perflab_aea_get_total_enqueued_styles() {
	$enqueued_styles = false;
	$blocking_assets = get_transient( 'aea_blocking_assets' );
	if ( is_array( $blocking_assets ) && isset( $blocking_assets['styles'] ) && is_array( $blocking_assets['styles'] ) ) {
		$enqueued_styles = count( $blocking_assets['styles'] );
	}
	return $enqueued_styles;
}

/**
 * Gets total size in bytes of Enqueued Styles.
 *
 * @since 1.0.0
 *
 * @return int|false Byte Total size or false if transient hasn't been set.
 */
function perflab_aea_get_total_size_bytes_enqueued_styles() {
	$total_size      = false;
	$blocking_assets = get_transient( 'aea_blocking_assets' );
	if ( is_array( $blocking_assets ) && isset( $blocking_assets['styles'] ) && is_array( $blocking_assets['styles'] ) ) {
		$total_size = 0;
		foreach ( $blocking_assets['styles'] as $enqueued_style ) {
			if ( is_array( $enqueued_style ) && array_key_exists( 'size', $enqueued_style ) && is_int( $enqueued_style['size'] ) ) {
				$total_size += $enqueued_style['size'];
			}
		}
	}
	return $total_size;
}

/**
 * Gets the content length of an asset.
 *
 * @since n.e.x.t
 *
 * @param string $resource_url URL of the resource.
 * @return int|null Returns the content length in bytes or null if it cannot be determined.
 */
function perflab_aea_get_asset_content_length( string $resource_url ): ?int {
	$head_response = wp_remote_head( $resource_url, array( 'timeout' => 10 ) );
	if ( is_wp_error( $head_response ) || 200 !== wp_remote_retrieve_response_code( $head_response ) ) {
		return null;
	}

	$content_length = wp_remote_retrieve_header( $head_response, 'content-length' );
	if ( is_array( $content_length ) && isset( $content_length[0] ) ) {
		$content_length = $content_length[0];
	}
	$content_length = (int) $content_length;
	if ( $content_length <= 0 ) {
		return null;
	}
	return $content_length;
}
