<?php
/**
 * Optimization Detective: OD_URL_Metrics_Post_Type class
 *
 * @package optimization-detective
 * @since 0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * URL Metrics Post Type.
 *
 * @since 0.1.0
 * @access private
 */
class OD_URL_Metrics_Post_Type {

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const SLUG = 'od_url_metrics';

	/**
	 * Event name (hook) for garbage collection of stale URL Metrics posts.
	 *
	 * @var string
	 */
	const GC_CRON_EVENT_NAME = 'od_url_metrics_gc';

	/**
	 * Recurrence for garbage collection of stale URL Metrics posts.
	 *
	 * @var string
	 */
	const GC_CRON_RECURRENCE = 'daily';

	/**
	 * Adds hooks.
	 *
	 * @since 0.1.0
	 */
	public static function add_hooks(): void {
		add_action( 'init', array( __CLASS__, 'register_post_type' ) );
		add_action( 'admin_init', array( __CLASS__, 'schedule_garbage_collection' ) );
		add_action( self::GC_CRON_EVENT_NAME, array( __CLASS__, 'delete_stale_posts' ) );
	}

	/**
	 * Registers post type for URL metrics storage.
	 *
	 * This the configuration for this post type is similar to the oembed_cache in core.
	 *
	 * @since 0.1.0
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::SLUG,
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
				'supports'         => array( 'title' ),
				// The original URL is stored in the post_title, and the post_name is a hash of the query vars.
			)
		);
	}

	/**
	 * Gets URL metrics post.
	 *
	 * @since 0.1.0
	 *
	 * @param string $slug URL metrics slug.
	 * @return WP_Post|null Post object if exists.
	 */
	public static function get_post( string $slug ): ?WP_Post {
		$post_query = new WP_Query(
			array(
				'post_type'              => self::SLUG,
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
	 * @since 0.1.0
	 *
	 * @param WP_Post $post URL metrics post.
	 * @return OD_URL_Metric[] URL metrics.
	 */
	public static function get_url_metrics_from_post( WP_Post $post ): array {
		$this_function = __METHOD__;
		$trigger_error = static function ( string $message, int $error_level = E_USER_NOTICE ) use ( $this_function ): void {
			wp_trigger_error( $this_function, esc_html( $message ), $error_level );
		};

		$url_metrics_data = json_decode( $post->post_content, true );
		if ( json_last_error() !== 0 ) {
			$trigger_error(
				sprintf(
					/* translators: 1: Post type slug, 2: Post ID, 3: JSON error message */
					__( 'Contents of %1$s post type (ID: %2$s) not valid JSON: %3$s', 'optimization-detective' ),
					self::SLUG,
					$post->ID,
					json_last_error_msg()
				),
				E_USER_WARNING
			);
			$url_metrics_data = array();
		} elseif ( ! is_array( $url_metrics_data ) ) {
			$trigger_error(
				sprintf(
					/* translators: %s is post type slug */
					__( 'Contents of %s post type was not a JSON array.', 'optimization-detective' ),
					self::SLUG
				),
				E_USER_WARNING
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
							$suffix = '';
							if ( isset( $url_metric_data['uuid'] ) && is_string( $url_metric_data['uuid'] ) ) {
								$suffix .= sprintf( ' (URL Metric UUID: %s)', $url_metric_data['uuid'] );
							}

							$trigger_error(
								sprintf(
									/* translators: 1: Post type slug. 2: Exception message. */
									__( 'Unexpected shape to JSON array in post_content of %1$s post type: %2$s', 'optimization-detective' ),
									OD_URL_Metrics_Post_Type::SLUG,
									$e->getMessage() . $suffix
								),
								// This is not a warning because schema changes will happen, and so it is expected
								// that this will result in existing URL metrics being invalidated.
								E_USER_NOTICE
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
	 * Stores URL metric by merging it with the other URL metrics which share the same normalized query vars.
	 *
	 * @since 0.1.0
	 * @todo There is duplicate logic here with od_handle_rest_request().
	 *
	 * @param string        $slug           Slug (hash of normalized query vars).
	 * @param OD_URL_Metric $new_url_metric New URL metric.
	 * @return int|WP_Error Post ID or WP_Error otherwise.
	 */
	public static function store_url_metric( string $slug, OD_URL_Metric $new_url_metric ) {
		$post_data = array(
			// The URL is supplied as the post title in order to aid with debugging. Note that an od-url-metrics post stores
			// multiple URL Metric instances, each of which also contains the URL for which the metric was captured. The URL
			// appearing in the post title is therefore the most recent URL seen for the URL Metrics which have the same
			// normalized query vars among them.
			'post_title' => $new_url_metric->get_url(),
		);

		$post = self::get_post( $slug );
		if ( $post instanceof WP_Post ) {
			$post_data['ID']        = $post->ID;
			$post_data['post_name'] = $post->post_name;
			$url_metrics            = self::get_url_metrics_from_post( $post );
		} else {
			$post_data['post_name'] = $slug;
			$url_metrics            = array();
		}

		$group_collection = new OD_URL_Metric_Group_Collection(
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
			JSON_UNESCAPED_SLASHES // No need for escaped slashes since not printed to frontend.
		);
		if ( ! is_string( $post_data['post_content'] ) ) {
			return new WP_Error( 'json_encode_error', json_last_error_msg() );
		}

		$has_kses = false !== has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $has_kses ) {
			// Prevent KSES from corrupting JSON in post_content.
			kses_remove_filters();
		}

		$post_data['post_type']   = self::SLUG;
		$post_data['post_status'] = 'publish';
		$slashed_post_data        = wp_slash( $post_data );
		if ( isset( $post_data['ID'] ) ) {
			$result = wp_update_post( $slashed_post_data, true );
		} else {
			$result = wp_insert_post( $slashed_post_data, true );
		}

		if ( $has_kses ) {
			kses_init_filters();
		}

		return $result;
	}

	/**
	 * Schedules garbage collection of stale URL Metrics.
	 *
	 * @since 0.1.0
	 */
	public static function schedule_garbage_collection(): void {
		if ( ! is_user_logged_in() ) {
			return;
		}

		// Unschedule any existing event which had a differing recurrence.
		$scheduled_event = wp_get_scheduled_event( self::GC_CRON_EVENT_NAME );
		if ( is_object( $scheduled_event ) && self::GC_CRON_RECURRENCE !== $scheduled_event->schedule ) {
			wp_unschedule_event( $scheduled_event->timestamp, self::GC_CRON_EVENT_NAME );
			$scheduled_event = false;
		}

		if ( false === $scheduled_event ) {
			wp_schedule_event( time(), self::GC_CRON_RECURRENCE, self::GC_CRON_EVENT_NAME );
		}
	}

	/**
	 * Deletes posts that have not been modified in the past month.
	 *
	 * @since 0.1.0
	 */
	public static function delete_stale_posts(): void {
		$one_month_ago = gmdate( 'Y-m-d H:i:s', strtotime( '-1 month' ) );

		$query = new WP_Query(
			array(
				'post_type'      => self::SLUG,
				'posts_per_page' => 100,
				'date_query'     => array(
					'column' => 'post_modified_gmt',
					'before' => $one_month_ago,
				),
			)
		);

		foreach ( $query->posts as $post ) {
			if ( $post instanceof WP_Post && self::SLUG === $post->post_type ) { // Sanity check.
				wp_delete_post( $post->ID, true );
			}
		}
	}

	/**
	 * Deletes all URL Metrics posts.
	 *
	 * This is used during uninstallation.
	 *
	 * @since 0.1.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public static function delete_all_posts(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		// Delete all related post meta for URL Metrics posts.
		$wpdb->query(
			$wpdb->prepare(
				"
				DELETE meta
				FROM $wpdb->postmeta AS meta
					INNER JOIN $wpdb->posts AS posts
						ON posts.ID = meta.post_id
				WHERE posts.post_type = %s;
				",
				self::SLUG
			)
		);

		// Delete all URL Metrics posts.
		$wpdb->delete(
			$wpdb->posts,
			array(
				'post_type' => self::SLUG,
			)
		);

		wp_cache_set_posts_last_changed();

		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
