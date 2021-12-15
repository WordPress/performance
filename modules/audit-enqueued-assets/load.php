<?php
/**
 * Module Name: Audit Enqueued Assets
 * Description: Adds a CSS and JS resource checker in Site Health checks.
 * Focus: site-health
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Audit enqueued scripts in the frontend. Ignore /wp-includes scripts.
 *
 * It will save information in a transient for 12 hours.
 *
 * @since 1.0.0
 */
function aea_audit_enqueued_scripts() {
	if ( ! is_admin() && ! get_transient( 'aea_enqueued_scripts' ) ) {
		global $wp_scripts;
		$enqueued_scripts = array();

		foreach ( $wp_scripts->queue as $handle ) {
			$src = $wp_scripts->registered[ $handle ]->src;
			if ( $src && ! strpos( $src, 'wp-includes' ) ) {
				$enqueued_scripts[] = $src;
			}
		}
		$expiration = apply_filters( 'aea_audit_enqueued_scripts_expiration_in_seconds', 12 * HOUR_IN_SECONDS );
		set_transient( 'aea_enqueued_scripts', $enqueued_scripts, $expiration );
	}
}
add_action( 'wp_print_scripts', 'aea_audit_enqueued_scripts' );

/**
 * Audit enqueued styles in the frontend. Ignore /wp-includes styles.
 *
 * It will save information in a transient for 12 hours.
 *
 * @since 1.0.0
 */
function aea_audit_enqueued_styles() {
	if ( ! is_admin() && ! get_transient( 'aea_enqueued_styles' ) ) {
		global $wp_styles;
		$enqueued_styles = array();
		foreach ( $wp_styles->queue as $handle ) {
			$src = $wp_styles->registered[ $handle ]->src;
			if ( $src && ! strpos( $src, 'wp-includes' ) ) {
				$enqueued_styles[] = $src;
			}
		}
		$expiration = apply_filters( 'aea_audit_enqueued_styles_expiration_in_seconds', 12 * HOUR_IN_SECONDS );
		set_transient( 'aea_enqueued_styles', $enqueued_styles, $expiration );
	}
}
add_action( 'wp_print_styles', 'aea_audit_enqueued_styles' );

/**
 * Gets total of enqueued scripts.
 *
 * @since 1.0.0
 *
 * @return int|false Number of total scripts or false if transient hasn't been set.
 */
function aea_get_total_enqueued_scripts() {
	$enqueued_scripts      = false;
	$list_enqueued_scripts = get_transient( 'aea_enqueued_scripts' );
	if ( $list_enqueued_scripts ) {
		$enqueued_scripts = count( $list_enqueued_scripts );
	}
	return $enqueued_scripts;
}

/**
 * Gets total of enqueued styles.
 *
 * @since 1.0.0
 *
 * @return int|false Number of total styles or false if transient hasn't been set.
 */
function aea_get_total_enqueued_styles() {
	$enqueued_styles      = false;
	$list_enqueued_styles = get_transient( 'aea_enqueued_styles' );
	if ( $list_enqueued_styles ) {
		$enqueued_styles = count( $list_enqueued_styles );
	}
	return $enqueued_styles;
}

/**
 * Adds tests to site health.
 *
 * @since 1.0.0
 *
 * @param array $tests Site Health Tests.
 * @return array
 */
function aea_add_enqueued_assets_test( $tests ) {
	$tests['direct']['enqueued_js_assets']  = array(
		'label' => esc_html__( 'JS assets', 'performance-lab' ),
		'test'  => 'aea_enqueued_js_assets_test',
	);
	$tests['direct']['enqueued_css_assets'] = array(
		'label' => esc_html__( 'CSS assets', 'performance-lab' ),
		'test'  => 'aea_enqueued_css_assets_test',
	);

	return $tests;
}
add_filter( 'site_status_tests', 'aea_add_enqueued_assets_test' );

/**
 * Invalidate both transients/cache.
 *
 * @since 1.0.0
 */
function invalidate_cache_transients() {
	delete_transient( 'aea_enqueued_scripts' );
	delete_transient( 'aea_enqueued_styles' );
}
add_action( 'switch_theme', 'invalidate_cache_transients' );
add_action( 'activated_plugin', 'invalidate_cache_transients' );
add_action( 'deactivated_plugin', 'invalidate_cache_transients' );

/**
 * Callback for enqueued_js_assets test.
 *
 * @since 1.0.0
 *
 * @return array
 */
function aea_enqueued_js_assets_test() {
	/**
	 * If the test didn't run yet, deactivate.
	 */
	$enqueued_scripts = aea_get_total_enqueued_scripts();
	if ( false === $enqueued_scripts ) {
		return array();
	}

	$result = array(
		'label'       => esc_html__( 'Enqueued JS assets', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => esc_html__( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			esc_html__( 'The amount of enqueued JS assets is acceptable.', 'performance-lab' )
		),
		'actions'     => '',
		'test'        => 'enqueued_js_assets',
	);

	if ( $enqueued_scripts > 10 ) {
		$result['status']         = 'recommended';
		$result['badge']['color'] = 'orange';
		$result['description']    = sprintf(
			/* translators: %s: Number of enqueued scripts */
			esc_html__( 'Your website enqueues %s scripts. Try to reduce the number of JS assets, or to concatenate them.', 'performance-lab' ),
			$enqueued_scripts
		);
		$result['actions'] .= sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			esc_html__( 'More info about performance optimization', 'performance-lab' )
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
function aea_enqueued_css_assets_test() {
	/**
	 * If the test didn't run yet, deactivate.
	 */
	$enqueued_styles = aea_get_total_enqueued_styles();
	if ( false === $enqueued_styles ) {
		return array();
	}

	$result = array(
		'label'       => esc_html__( 'Enqueued CSS assets', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => esc_html__( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => sprintf(
			'<p>%s</p>',
			esc_html__( 'The amount of enqueued CSS assets is acceptable.', 'performance-lab' )
		),
		'actions'     => '',
		'test'        => 'enqueued_css_assets',
	);

	if ( $enqueued_styles > 10 ) {
		$result['status']         = 'recommended';
		$result['badge']['color'] = 'orange';
		$result['description']    = sprintf(
			/* translators: %s: Number of enqueued styles */
			esc_html__( 'Your website enqueues %s styles. Try to reduce the number of CSS assets, or to concatenate them.', 'performance-lab' ),
			$enqueued_styles
		);
		$result['actions'] .= sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			esc_html__( 'More info about performance optimization', 'performance-lab' )
		);
	}

	return $result;
}

