<?php
/**
 * Helper functions for Performance Dashboard
 *
 * @package performance-dashboard
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Displays the HTML generator meta tag for the Performance Dashboard plugin.
 *
 * See {@see 'wp_head'}.
 *
 * @since 0.1.0
 */
function performance_dashboard_render_generator_meta_tag(): void {
	// Use the plugin slug as it is immutable.
	echo '<meta name="generator" content="performance-dashboard ' . esc_attr( PERFORMANCE_DASHBOARD_VERSION ) . '">' . "\n";
}

/**
 * Adds a performance dashboard widget.
 *
 * @since 0.1.0
 */
function performance_dashboard_add_dashboard_widget(): void {
	wp_add_dashboard_widget(
		'performance-dashboard-widget',
		__( 'Performance Overview', 'performance-dashboard' ),
		'performance_dashboard_render_dashboard_widget'
	);
}

/**
 * Renders the performance dashboard widget.
 *
 * @since 0.1.0
 */
function performance_dashboard_render_dashboard_widget(): void {
	$asset_file = plugin_dir_path( __FILE__ ) . 'build/performance-dashboard-widget.asset.php';

	$asset = file_exists( $asset_file ) ? require $asset_file : array(
		'dependencies' => array(),
		'version'      => false,
	);

	wp_enqueue_style( 'wp-components' );

	wp_enqueue_script(
		'performance-dashboard-widget',
		plugin_dir_url( __FILE__ ) . 'build/performance-dashboard-widget.js',
		$asset['dependencies'],
		$asset['version'],
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	$recent_entries = get_posts(
		array(
			'post_type'              => OD_URL_Metrics_Post_Type::SLUG,
			'post_status'            => 'publish',
			'numberposts'            => 10,
			'suppress_filters'       => false,
			'no_found_rows'          => true,
			'cache_results'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'lazy_load_term_meta'    => false,
		)
	);

	$options = array_map(
		static function ( $entry ) {
			return array(
				'value' => $entry->ID,
				'label' => $entry->post_title,
			);
		},
		$recent_entries
	);

	wp_localize_script(
		'performance-dashboard-widget',
		'performanceDashboardWidget',
		array(
			'options'  => $options,
			'homeUrl'  => home_url( '/' ),
			'postType' => OD_URL_Metrics_Post_Type::SLUG,
		)
	);
	?>
	<div id="performance-dashboard-widget">
		<?php esc_html_e( 'Loading…', 'performance-dashboard' ); ?>
	</div>
	<?php
}

/**
 * Adds a performance dashboard admin menu item.
 *
 * @since 0.1.0
 */
function performance_dashboard_add_submenu_page(): void {
	add_submenu_page(
		'index.php',
		__( 'Performance', 'performance-dashboard' ),
		__( 'Performance', 'performance-dashboard' ),
		'manage_options',
		'performance-dashboard',
		'performance_dashboard_render_dashboard_page'
	);
}

/**
 * Renders the performance dashboard page.
 *
 * @since 0.1.0
 */
function performance_dashboard_render_dashboard_page(): void {
	$asset_file = plugin_dir_path( __FILE__ ) . 'build/performance-dashboard.asset.php';

	$asset = file_exists( $asset_file ) ? require $asset_file : array(
		'dependencies' => array(),
		'version'      => false,
	);

	wp_enqueue_style( 'wp-components' );

	wp_enqueue_script(
		'performance-dashboard',
		plugin_dir_url( __FILE__ ) . 'build/performance-dashboard.js',
		$asset['dependencies'],
		$asset['version'],
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	$recent_entries = get_posts(
		array(
			'post_type'              => OD_URL_Metrics_Post_Type::SLUG,
			'post_status'            => 'publish',
			'numberposts'            => 10,
			'suppress_filters'       => false,
			'no_found_rows'          => true,
			'cache_results'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'lazy_load_term_meta'    => false,
		)
	);

	$options = array_map(
		static function ( $entry ) {
			return array(
				'value' => $entry->ID,
				'label' => $entry->post_title,
			);
		},
		$recent_entries
	);

	wp_localize_script(
		'performance-dashboard',
		'performanceDashboard',
		array(
			'options'  => $options,
			'homeUrl'  => home_url( '/' ),
			'postType' => OD_URL_Metrics_Post_Type::SLUG,
		)
	);
	?>
	<div class="wrap">
		<h2><?php esc_html_e( 'Performance Dashboard', 'performance-dashboard' ); ?></h2>
		<div id="performance-dashboard">
			<?php esc_html_e( 'Loading…', 'performance-dashboard' ); ?>
		</div>
	</div>
	<?php
}

/**
 * Registers post type for URL metrics storage.
 *
 * This the configuration for this post type is similar to the oembed_cache in core.
 *
 * @since 0.1.0
 */
function performance_dashboard_register_post_type(): void {
	register_post_type(
		'perf-dash-data',
		array(
			'labels'           => array(
				'name'          => __( 'Performance Metrics', 'performance-dashboard' ),
				'singular_name' => __( 'Performance Metrics', 'performance-dashboard' ),
			),
			'public'           => false,
			'hierarchical'     => false,
			'rewrite'          => false,
			'query_var'        => false,
			'delete_with_user' => false,
			'can_export'       => false,
			'supports'         => array( 'title' ),
			// The original URL is stored in the post_title, and the post_name is a hash of the query vars.
		)
	);
}

/**
 * Stores incoming URL metrics in a way that's suitable for Performance Dashboard.
 *
 * @since 0.1.0
 *
 * @param OD_URL_Metric $url_metric URL metric from Optimization Detective.
 */
function performance_dashboard_store_data( OD_URL_Metric $url_metric ): void {
	// TODO: Store form factor and effective connection type à la CRuX.

	$viewport_width = $url_metric->get_viewport_width();
	$web_vitals     = $url_metric->get_web_vitals();
	$timestamp      = $url_metric->get_timestamp();
	$url            = $url_metric->get_url();
}

/**
 * Registers custom REST routes.
 *
 * @since 0.1.0
 */
function performance_dashboard_register_rest_routes(): void {
	$controller = new Performance_Dashboard_REST_Controller();
	$controller->register_routes();
}
