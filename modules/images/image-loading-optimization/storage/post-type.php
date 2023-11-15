<?php
/**
 * Metrics storage post type.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

const ILO_PAGE_METRICS_POST_TYPE = 'ilo_page_metrics';

/**
 * Registers post type for page metrics storage.
 *
 * This the configuration for this post type is similar to the oembed_cache in core.
 *
 * @since n.e.x.t
 * @access private
 */
function ilo_register_page_metrics_post_type() /*: void (in PHP 7.1) */ {
	register_post_type(
		ILO_PAGE_METRICS_POST_TYPE,
		array(
			'labels'           => array(
				'name'          => __( 'Page Metrics', 'performance-lab' ),
				'singular_name' => __( 'Page Metrics', 'performance-lab' ),
			),
			'public'           => false,
			'hierarchical'     => false,
			'rewrite'          => false,
			'query_var'        => false,
			'delete_with_user' => false,
			'can_export'       => false,
			'supports'         => array( 'title' ), // The original URL is stored in the post_title, and the post_name is a hash of the query vars.
		)
	);
}
add_action( 'init', 'ilo_register_page_metrics_post_type' );

/**
 * Gets page metrics post.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $slug Page metrics slug.
 * @return WP_Post|null Post object if exists.
 */
function ilo_get_page_metrics_post( string $slug ) /*: ?WP_Post (in PHP 7.1) */ {
	$post_query = new WP_Query(
		array(
			'post_type'              => ILO_PAGE_METRICS_POST_TYPE,
			'post_status'            => 'publish',
			'name'                   => $slug,
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'cache_results'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'lazy_load_term_meta'    => false,
		)
	);

	$post = current( $post_query->posts );
	if ( $post instanceof WP_Post ) {
		return $post;
	} else {
		return null;
	}
}

/**
 * Parses post content in page metrics post.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param WP_Post $post Page metrics post.
 * @return array Page metrics.
 */
function ilo_parse_stored_page_metrics( WP_Post $post ): array {
	$this_function = __FUNCTION__;
	$trigger_error = static function ( $error ) use ( $this_function ) {
		if ( function_exists( 'wp_trigger_error' ) ) {
			wp_trigger_error( $this_function, esc_html( $error ), E_USER_WARNING );
		}
	};

	$page_metrics = json_decode( $post->post_content, true );
	if ( json_last_error() ) {
		$trigger_error(
			sprintf(
				/* translators: 1: Post type slug, 2: JSON error message */
				__( 'Contents of %1$s post type not valid JSON: %2$s', 'performance-lab' ),
				ILO_PAGE_METRICS_POST_TYPE,
				json_last_error_msg()
			)
		);
		$page_metrics = array();
	} elseif ( ! is_array( $page_metrics ) ) {
		$trigger_error(
			sprintf(
				/* translators: %s is post type slug */
				__( 'Contents of %s post type was not a JSON array.', 'performance-lab' ),
				ILO_PAGE_METRICS_POST_TYPE
			)
		);
		$page_metrics = array();
	}
	return $page_metrics;
}

/**
 * Stores page metric by merging it with the other page metrics for a given URL.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $url                   URL for the page metrics. This is used purely as metadata.
 * @param string $slug                  Page metrics slug (computed from query vars).
 * @param array  $validated_page_metric Validated page metric. See JSON Schema defined in ilo_register_endpoint().
 * @return int|WP_Error Post ID or WP_Error otherwise.
 */
function ilo_store_page_metric( string $url, string $slug, array $validated_page_metric ) /*: int|WP_Error (in PHP 8) */ {
	$validated_page_metric['timestamp'] = microtime( true );

	// TODO: What about storing a version identifier?
	$post_data = array(
		'post_title' => $url, // TODO: Should we keep this? It can help with debugging.
	);

	$post = ilo_get_page_metrics_post( $slug );

	if ( $post instanceof WP_Post ) {
		$post_data['ID']        = $post->ID;
		$post_data['post_name'] = $post->post_name;
		$page_metrics           = ilo_parse_stored_page_metrics( $post );
	} else {
		$post_data['post_name'] = $slug;
		$page_metrics           = array();
	}

	$page_metrics = ilo_unshift_page_metrics( $page_metrics, $validated_page_metric );

	$post_data['post_content'] = wp_json_encode( $page_metrics, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // TODO: No need for pretty-printing.

	$has_kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
	if ( $has_kses ) {
		// Prevent KSES from corrupting JSON in post_content.
		kses_remove_filters();
	}

	$post_data['post_type']   = ILO_PAGE_METRICS_POST_TYPE;
	$post_data['post_status'] = 'publish';
	if ( isset( $post_data['ID'] ) ) {
		$result = wp_update_post( wp_slash( $post_data ), true );
	} else {
		$result = wp_insert_post( wp_slash( $post_data ), true );
	}

	if ( $has_kses ) {
		kses_init_filters();
	}

	return $result;
}
