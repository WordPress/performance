<?php
/**
 * Optimization Detective: OD_URL_Metric_Store_Request_Context class
 *
 * @package optimization-detective
 * @since 0.7.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Context for when a URL Metric is successfully stored via the REST API.
 *
 * @since 0.7.0
 *
 * @property-read WP_REST_Request<array<string, mixed>> $request                     Request.
 * @property-read positive-int                          $url_metrics_id              ID for the od_url_metrics post.
 * @property-read OD_URL_Metric_Group_Collection        $url_metric_group_collection URL Metric group collection.
 * @property-read OD_URL_Metric_Group                   $url_metric_group            URL Metric group.
 * @property-read OD_URL_Metric                         $url_metric                  URL Metric.
 * @property-read positive-int                          $post_id                     Deprecated alias for the $url_metrics_id property.
 */
final class OD_URL_Metric_Store_Request_Context {

	/**
	 * Request.
	 *
	 * @since 0.7.0
	 * @var WP_REST_Request<array<string, mixed>>
	 */
	private $request;

	/**
	 * ID for the od_url_metrics post.
	 *
	 * This was originally $post_id which was introduced in 0.7.0.
	 *
	 * @since 1.0.0
	 * @var positive-int
	 */
	private $url_metrics_id;

	/**
	 * URL Metric group collection.
	 *
	 * @since 0.7.0
	 * @var OD_URL_Metric_Group_Collection
	 */
	private $url_metric_group_collection;

	/**
	 * URL Metric group.
	 *
	 * @since 0.7.0
	 * @var OD_URL_Metric_Group
	 */
	private $url_metric_group;

	/**
	 * URL Metric.
	 *
	 * @since 0.7.0
	 * @var OD_URL_Metric
	 */
	private $url_metric;

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param WP_REST_Request                $request                     REST API request.
	 * @param positive-int                   $url_metrics_id              ID for the URL Metric post.
	 * @param OD_URL_Metric_Group_Collection $url_metric_group_collection URL Metric group collection.
	 * @param OD_URL_Metric_Group            $url_metric_group            URL Metric group.
	 * @param OD_URL_Metric                  $url_metric                  URL Metric.
	 */
	public function __construct( WP_REST_Request $request, int $url_metrics_id, OD_URL_Metric_Group_Collection $url_metric_group_collection, OD_URL_Metric_Group $url_metric_group, OD_URL_Metric $url_metric ) {
		$this->request                     = $request;
		$this->url_metrics_id              = $url_metrics_id;
		$this->url_metric_group_collection = $url_metric_group_collection;
		$this->url_metric_group            = $url_metric_group;
		$this->url_metric                  = $url_metric;
	}

	/**
	 * Gets a property.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Property name.
	 * @return mixed Property value.
	 *
	 * @throws Error When property is unknown.
	 */
	public function __get( string $name ) {
		switch ( $name ) {
			case 'request':
				return $this->request;
			case 'url_metrics_id':
				return $this->url_metrics_id;
			case 'url_metric_group_collection':
				return $this->url_metric_group_collection;
			case 'url_metric_group':
				return $this->url_metric_group;
			case 'url_metric':
				return $this->url_metric;
			case 'post_id':
				_doing_it_wrong(
					esc_html( __CLASS__ . '::$' . $name ),
					esc_html(
						sprintf(
							/* translators: %s is class member variable name */
							__( 'Use %s instead.', 'optimization-detective' ),
							__CLASS__ . '::$url_metrics_id'
						)
					),
					'optimization-detective 1.0.0'
				);
				return $this->url_metrics_id;
			default:
				throw new Error(
					esc_html(
						sprintf(
							/* translators: %s is class member variable name */
							__( 'Unknown property %s.', 'optimization-detective' ),
							__CLASS__ . '::$' . $name
						)
					)
				);
		}
	}
}
