<?php
/**
 * Optimizing for Optimization Detective.
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

function od_add_submenu_page() {
	add_submenu_page(
		'index.php',
		__( 'Performance', 'optimization-detective' ),
		__( 'Performance', 'optimization-detective' ),
		'manage_options',
		'od-performance-dashboard',
		'od_render_dashboard_page'
	);
}

add_action( 'admin_menu', 'od_add_submenu_page' );

function od_render_dashboard_page() {
	$asset_file = plugin_dir_path( __FILE__ ) . 'build/performance-dashboard.asset.php';

	if ( ! file_exists( $asset_file ) ) {
		return;
	}

	$asset = include $asset_file;

	wp_enqueue_style( 'wp-components' );

	wp_enqueue_script(
		'od-performance-dashboard',
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

	$options = array_map( function( $entry ) {
		return array(
			'value' => $entry->ID,
			'label' => $entry->post_title,
		);
	}, $recent_entries );

	wp_localize_script(
		'od-performance-dashboard',
		'performanceDashboard',
		array(
			'options'  => $options,
			'homeUrl'  => home_url( '/' ),
			'postType' => OD_URL_Metrics_Post_Type::SLUG,
		)
	);
	?>
	<div class="wrap">
		<h2><?php esc_html_e( 'Performance Dashboard', 'optimization-detective' ); ?></h2>
		<div id="od-performance-dashboard">
			<?php esc_html_e( 'Loading…', 'optimization-detective' ); ?>
		</div>
	</div>
	<?php
}
