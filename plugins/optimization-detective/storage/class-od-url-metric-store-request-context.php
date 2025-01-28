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
 * @access private
 */
final class OD_URL_Metric_Store_Request_Context {

	/**
	 * Request.
	 *
	 * @var WP_REST_Request<array<string, mixed>>
	 * @readonly
	 */
	public $request;

	/**
	 * ID for the URL Metric post.
	 *
	 * @var int
	 * @readonly
	 */
	public $post_id;

	/**
	 * URL Metric group collection.
	 *
	 * @var OD_URL_Metric_Group_Collection
	 * @readonly
	 */
	public $url_metric_group_collection;

	/**
	 * URL Metric group.
	 *
	 * @var OD_URL_Metric_Group
	 * @readonly
	 */
	public $url_metric_group;

	/**
	 * URL Metric.
	 *
	 * @var OD_URL_Metric
	 * @readonly
	 */
	public $url_metric;

	/**
	 * Constructor.
	 *
	 * @phpstan-param WP_REST_Request<array<string, mixed>> $request
	 *
	 * @param WP_REST_Request                $request                     REST API request.
	 * @param int                            $post_id                     ID for the URL Metric post.
	 * @param OD_URL_Metric_Group_Collection $url_metric_group_collection URL Metric group collection.
	 * @param OD_URL_Metric_Group            $url_metric_group            URL Metric group.
	 * @param OD_URL_Metric                  $url_metric                  URL Metric.
	 */
	public function __construct( WP_REST_Request $request, int $post_id, OD_URL_Metric_Group_Collection $url_metric_group_collection, OD_URL_Metric_Group $url_metric_group, OD_URL_Metric $url_metric ) {
		$this->request                     = $request;
		$this->post_id                     = $post_id;
		$this->url_metric_group_collection = $url_metric_group_collection;
		$this->url_metric_group            = $url_metric_group;
		$this->url_metric                  = $url_metric;
	}
}
