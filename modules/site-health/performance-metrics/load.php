<?php
/**
 * Module Name: Performance Metrics
 * Description: Adds a new tab to the Site Health page with key performance metrics.
 * Experimental: No
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Add a new tab to the Site Health page with key performance metrics.
 */
function performance_lab_pm_add_site_health_metrics_tab( $tabs ) {
	$tabs['performance-metrics-tab'] = esc_html_x( 'Performance Metrics', 'Site Health', 'performance-lab' );

	return $tabs;
}
add_filter( 'site_health_navigation_tabs', 'performance_lab_pm_add_site_health_metrics_tab' );

/**
 * Gathering the performance metrics.
 */
function performance_lab_pm_get_site_health_metrics() {
	$metrics = array();

	$metrics['loaded-assets'] = array(
		'label'  => esc_html__( 'Loaded Assets', 'performance-lab' ),
		'fields' => array(
			'scripts-number' => array(
				'label' => esc_html__( 'Number of loaded scripts', 'performance-lab' ),
				'value' => 0,
			),
			'scripts-size'   => array(
				'label' => esc_html__( 'Total size of loaded scripts', 'performance-lab' ),
				'value' => 0,
			),
			'styles-number'  => array(
				'label' => esc_html__( 'Number of loaded styles', 'performance-lab' ),
				'value' => 0,
			),
			'styles-size'    => array(
				'label' => esc_html__( 'Total size of loaded styles', 'performance-lab' ),
				'value' => 0,
			),
		),
	);

	$metrics['images'] = array(
		'label'  => esc_html__( 'Images', 'performance-lab' ),
		'fields' => array(
			'webp-support' => array(
				'label' => esc_html__( 'WebP Support', 'performance-lab' ),
				'value' => 'Yes',
			),
		),
	);

	return $metrics;
}

/**
 * Add the content for the new tab.
 */
function performance_lab_pm_add_site_health_metrics_tab_content( $tab ) {
	if ( 'performance-metrics-tab' !== $tab ) {
		return;
	}

	// Include the view.
	include trailingslashit( plugin_dir_path( __FILE__ ) ) . 'performance-metrics-view.php';
}
add_filter( 'site_health_tab_content', 'performance_lab_pm_add_site_health_metrics_tab_content' );

