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
 * Register post type for page metrics storage.
 *
 * This the configuration for this post type is similar to the oembed_cache in core.
 */
function ilo_register_page_metrics_post_type() {
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
 * Get page metrics post.
 *
 * @param string $slug Page metrics slug.
 * @return WP_Post|null Post object if exists.
 */
function ilo_get_page_metrics_post( $slug ) {
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
 * @param WP_Post $post Page metrics post.
 * @return array|WP_Error Page metrics when valid, or WP_Error otherwise.
 */
function ilo_parse_stored_page_metrics( WP_Post $post ) {
	$page_metrics = json_decode( $post->post_content, true );
	if ( json_last_error() ) {
		return new WP_Error(
			'page_metrics_json_parse_error',
			sprintf(
				/* translators: 1: Post type slug, 2: JSON error message */
				__( 'Contents of %1$s post type not valid JSON: %2$s', 'performance-lab' ),
				ILO_PAGE_METRICS_POST_TYPE,
				json_last_error_msg()
			)
		);
	}
	if ( ! is_array( $page_metrics ) ) {
		return new WP_Error(
			'page_metrics_invalid_data_format',
			sprintf(
				/* translators: %s is post type slug */
				__( 'Contents of %s post type was not a JSON array.', 'performance-lab' ),
				ILO_PAGE_METRICS_POST_TYPE
			)
		);
	}
	return $page_metrics;
}

/**
 * Gets page metrics for a slug.
 *
 * This is a convenience abstractions for lower-level functions.
 *
 * @see ilo_get_page_metrics_post()
 * @see ilo_parse_stored_page_metrics()
 *
 * @param string $slug Page metrics slug.
 * @return array Page metrics data, or empty array if invalid.
 */
function ilo_get_page_metrics_data( $slug ) {
	$post = ilo_get_page_metrics_post( $slug );
	if ( ! ( $post instanceof WP_Post ) ) {
		return array();
	}
	$data = ilo_parse_stored_page_metrics( $post );
	if ( ! is_array( $data ) ) {
		return array();
	}
	return $data;
}

/**
 * Stores page metric by merging it with the other page metrics for a given URL.
 *
 * The $validated_page_metric parameter has the following array shape:
 *
 * {
 *      'viewport': array{
 *          'width': int,
 *          'height': int
 *      },
 *      'elements': array
 * }
 *
 * @param string $url                   URL for the page metrics. This is used purely as metadata.
 * @param string $slug                  Page metrics slug (computed from query vars).
 * @param array  $validated_page_metric Page metric, already validated by REST API.
 * @return int|WP_Error Post ID or WP_Error otherwise.
 */
function ilo_store_page_metric( $url, $slug, array $validated_page_metric ) {
	$validated_page_metric['timestamp'] = microtime( true );

	// TODO: What about storing a version identifier?
	$post_data = array(
		'post_title' => $url, // TODO: Should we keep this? It can help with debugging.
	);

	$post = ilo_get_page_metrics_post( $slug );

	if ( $post instanceof WP_Post ) {
		$post_data['ID']        = $post->ID;
		$post_data['post_name'] = $post->post_name;

		$page_metrics = ilo_parse_stored_page_metrics( $post );
		if ( $page_metrics instanceof WP_Error ) {
			if ( function_exists( 'wp_trigger_error' ) ) {
				wp_trigger_error( __FUNCTION__, esc_html( $page_metrics->get_error_message() ) );
			}
			$page_metrics = array();
		}
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
