<?php
/**
 * Optimization Detective: OD_URL_Metric_Stored_Context class
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context for when a URL metric is successfully stored via the REST API.
 *
 * @since n.e.x.t
 * @access private
 */
final class OD_URL_Metric_Stored_Context {

	/**
	 * Request.
	 *
	 * @var WP_REST_Request<array<string, mixed>>
	 * @readonly
	 */
	public $request;

	/**
	 * ID for the URL metric post.
	 *
	 * @var int
	 * @readonly
	 */
	public $post_id;

	/**
	 * URL metric group collection.
	 *
	 * @var OD_URL_Metric_Group_Collection
	 * @readonly
	 */
	public $url_metric_group_collection;

	/**
	 * URL metric group.
	 *
	 * @var OD_URL_Metric_Group
	 * @readonly
	 */
	public $url_metric_group;

	/**
	 * URL metric.
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
	 * @param int                            $post_id                     ID for the URL metric post.
	 * @param OD_URL_Metric_Group_Collection $url_metric_group_collection URL metric group collection.
	 * @param OD_URL_Metric_Group            $url_metric_group            URL metric group.
	 * @param OD_URL_Metric                  $url_metric                  URL metric.
	 */
	public function __construct( WP_REST_Request $request, int $post_id, OD_URL_Metric_Group_Collection $url_metric_group_collection, OD_URL_Metric_Group $url_metric_group, OD_URL_Metric $url_metric ) {
		$this->request                     = $request;
		$this->post_id                     = $post_id;
		$this->url_metric_group_collection = $url_metric_group_collection;
		$this->url_metric_group            = $url_metric_group;
		$this->url_metric                  = $url_metric;
	}
}
