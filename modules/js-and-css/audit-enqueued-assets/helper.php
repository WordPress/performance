<?php
/**
 * Helper functions used for Enqueued Assets Health Check.
 *
 * @package performance-lab
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Callback for enqueued_js_assets test.
 *
 * @since 1.0.0
 *
 * @return array
 */
function perflab_aea_enqueued_js_assets_test() {
	/**
	 * If the test didn't run yet, deactivate.
	 */
	$enqueued_scripts = perflab_aea_get_total_enqueued_scripts();
	if ( false === $enqueued_scripts ) {
		return array();
	}

	$result = array(
		'label'       => __( 'Enqueued scripts', 'performance-lab' ),
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
						'The amount of %1$s enqueued script (size: %2$s) is acceptable.',
						'The amount of %1$s enqueued scripts (size: %2$s) is acceptable.',
						$enqueued_scripts,
						'performance-lab'
					),
					$enqueued_scripts,
					size_format( perflab_aea_get_total_size_bytes_enqueued_scripts() )
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

	if ( $enqueued_scripts > $scripts_threshold || perflab_aea_get_total_size_bytes_enqueued_scripts() > $scripts_size_threshold ) {
		$result['status'] = 'recommended';

		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of enqueued styles. 2.Styles size. */
					_n(
						'Your website enqueues %1$s script (size: %2$s). Try to reduce the number or to concatenate them.',
						'Your website enqueues %1$s scripts (size: %2$s). Try to reduce the number or to concatenate them.',
						$enqueued_scripts,
						'performance-lab'
					),
					$enqueued_scripts,
					size_format( perflab_aea_get_total_size_bytes_enqueued_scripts() )
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
 * @return array
 */
function perflab_aea_enqueued_css_assets_test() {
	/**
	 * If the test didn't run yet, deactivate.
	 */
	$enqueued_styles = perflab_aea_get_total_enqueued_styles();
	if ( false === $enqueued_styles ) {
		return array();
	}
	$result = array(
		'label'       => __( 'Enqueued styles', 'performance-lab' ),
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
						'The amount of %1$s enqueued style (size: %2$s) is acceptable.',
						'The amount of %1$s enqueued styles (size: %2$s) is acceptable.',
						$enqueued_styles,
						'performance-lab'
					),
					$enqueued_styles,
					size_format( perflab_aea_get_total_size_bytes_enqueued_styles() )
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
						'Your website enqueues %1$s style (size: %2$s). Try to reduce the number or to concatenate them.',
						'Your website enqueues %1$s styles (size: %2$s). Try to reduce the number or to concatenate them.',
						$enqueued_styles,
						'performance-lab'
					),
					$enqueued_styles,
					size_format( perflab_aea_get_total_size_bytes_enqueued_styles() )
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
	$enqueued_scripts      = false;
	$list_enqueued_scripts = get_transient( 'aea_enqueued_front_page_scripts' );
	if ( $list_enqueued_scripts ) {
		$enqueued_scripts = count( $list_enqueued_scripts );
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
	$total_size            = false;
	$list_enqueued_scripts = get_transient( 'aea_enqueued_front_page_scripts' );
	if ( $list_enqueued_scripts ) {
		$total_size = 0;
		foreach ( $list_enqueued_scripts as $enqueued_script ) {
			$total_size += $enqueued_script['size'];
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
	$enqueued_styles      = false;
	$list_enqueued_styles = get_transient( 'aea_enqueued_front_page_styles' );
	if ( $list_enqueued_styles ) {
		$enqueued_styles = count( $list_enqueued_styles );
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
	$total_size           = false;
	$list_enqueued_styles = get_transient( 'aea_enqueued_front_page_styles' );
	if ( $list_enqueued_styles ) {
		$total_size = 0;
		foreach ( $list_enqueued_styles as $enqueued_style ) {
			$total_size += $enqueued_style['size'];
		}
	}
	return $total_size;
}

/**
 * Convert full URL paths to absolute paths.
 * Covers Standard WP configuration, wp-content outside WP directories and subdirectories.
 * Ex: https://example.com/content/themes/, https://example.com/wp/wp-includes/
 *
 * @since 1.0.0
 *
 * @param string $resource_url URl resource link.
 * @return string Returns absolute path to the resource.
 */
function perflab_aea_get_path_from_resource_url( $resource_url ) {
	if ( ! $resource_url ) {
		return '';
	}

	// Different content folder ex. /content/.
	if ( 0 === strpos( $resource_url, content_url() ) ) {
		return WP_CONTENT_DIR . substr( $resource_url, strlen( content_url() ) );
	}

	// wp-content in a subdirectory. ex. /blog/wp-content/.
	$site_url = untrailingslashit( site_url() );
	if ( 0 === strpos( $resource_url, $site_url ) ) {
		return untrailingslashit( ABSPATH ) . substr( $resource_url, strlen( $site_url ) );
	}

	// Standard wp-content configuration.
	return untrailingslashit( ABSPATH ) . wp_make_link_relative( $resource_url );
}
