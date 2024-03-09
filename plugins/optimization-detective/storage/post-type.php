<?php
/**
 * Metrics storage post type.
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

const OD_URL_METRICS_POST_TYPE = 'od_url_metrics';

/**
 * Registers post type for URL metrics storage.
 *
 * This the configuration for this post type is similar to the oembed_cache in core.
 *
 * @since n.e.x.t
 * @access private
 */
function od_register_url_metrics_post_type() {
	register_post_type(
		OD_URL_METRICS_POST_TYPE,
		array(
			'labels'           => array(
				'name'          => __( 'URL Metrics', 'optimization-detective' ),
				'singular_name' => __( 'URL Metrics', 'optimization-detective' ),
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
add_action( 'init', 'od_register_url_metrics_post_type' );

/**
 * Gets URL metrics post.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string $slug URL metrics slug.
 * @return WP_Post|null Post object if exists.
 */
function od_get_url_metrics_post( string $slug ) {
	$post_query = new WP_Query(
		array(
			'post_type'              => OD_URL_METRICS_POST_TYPE,
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
 * Parses post content in URL metrics post.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param WP_Post $post URL metrics post.
 * @return OD_URL_Metric[] URL metrics.
 */
function od_parse_stored_url_metrics( WP_Post $post ): array {
	$this_function = __FUNCTION__;
	$trigger_error = static function ( $error ) use ( $this_function ) {
		if ( function_exists( 'wp_trigger_error' ) ) {
			wp_trigger_error( $this_function, esc_html( $error ), E_USER_WARNING );
		}
	};

	$url_metrics_data = json_decode( $post->post_content, true );
	if ( json_last_error() ) {
		$trigger_error(
			sprintf(
				/* translators: 1: Post type slug, 2: Post ID, 3: JSON error message */
				__( 'Contents of %1$s post type (ID: %2$s) not valid JSON: %3$s', 'optimization-detective' ),
				OD_URL_METRICS_POST_TYPE,
				$post->ID,
				json_last_error_msg()
			)
		);
		$url_metrics_data = array();
	} elseif ( ! is_array( $url_metrics_data ) ) {
		$trigger_error(
			sprintf(
				/* translators: %s is post type slug */
				__( 'Contents of %s post type was not a JSON array.', 'optimization-detective' ),
				OD_URL_METRICS_POST_TYPE
			)
		);
		$url_metrics_data = array();
	}

	return array_values(
		array_filter(
			array_map(
				static function ( $url_metric_data ) use ( $trigger_error ) {
					if ( ! is_array( $url_metric_data ) ) {
						return null;
					}

					try {
						return new OD_URL_Metric( $url_metric_data );
					} catch ( OD_Data_Validation_Exception $e ) {
						$trigger_error(
							sprintf(
								/* translators: 1: Post type slug. 2: Exception message. */
								__( 'Unexpected shape to JSON array in post_content of %1$s post type: %2$s', 'optimization-detective' ),
								OD_URL_METRICS_POST_TYPE,
								$e->getMessage()
							)
						);
						return null;
					}
				},
				$url_metrics_data
			)
		)
	);
}

/**
 * Stores URL metric by merging it with the other URL metrics for a given URL.
 *
 * @since n.e.x.t
 * @access private
 *
 * @param string         $url            URL for the URL metrics. This is used purely as metadata.
 * @param string         $slug           URL metrics slug (computed from query vars).
 * @param OD_URL_Metric $new_url_metric New URL metric.
 * @return int|WP_Error Post ID or WP_Error otherwise.
 */
function od_store_url_metric( string $url, string $slug, OD_URL_Metric $new_url_metric ) {

	// TODO: What about storing a version identifier?
	$post_data = array(
		'post_title' => $url, // TODO: Should we keep this? It can help with debugging.
	);

	$post = od_get_url_metrics_post( $slug );

	if ( $post instanceof WP_Post ) {
		$post_data['ID']        = $post->ID;
		$post_data['post_name'] = $post->post_name;
		$url_metrics            = od_parse_stored_url_metrics( $post );
	} else {
		$post_data['post_name'] = $slug;
		$url_metrics            = array();
	}

	$group_collection = new OD_URL_Metrics_Group_Collection(
		$url_metrics,
		od_get_breakpoint_max_widths(),
		od_get_url_metrics_breakpoint_sample_size(),
		od_get_url_metric_freshness_ttl()
	);

	try {
		$group = $group_collection->get_group_for_viewport_width( $new_url_metric->get_viewport_width() );
		$group->add_url_metric( $new_url_metric );
	} catch ( InvalidArgumentException $e ) {
		return new WP_Error( 'invalid_url_metric', $e->getMessage() );
	}

	$post_data['post_content'] = wp_json_encode(
		array_map(
			static function ( OD_URL_Metric $url_metric ): array {
				return $url_metric->jsonSerialize();
			},
			$group_collection->get_flattened_url_metrics()
		),
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES // TODO: No need for pretty-printing.
	);

	$has_kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
	if ( $has_kses ) {
		// Prevent KSES from corrupting JSON in post_content.
		kses_remove_filters();
	}

	$post_data['post_type']   = OD_URL_METRICS_POST_TYPE;
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
