<?php
/**
 * Optimization Detective: OD_Template_Optimization_Context class
 *
 * @package optimization-detective
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Context for optimizing a template.
 *
 * @since 1.0.0
 *
 * @property-read OD_URL_Metric_Group_Collection $url_metric_group_collection URL Metric group collection.
 * @property-read positive-int|null              $url_metrics_id              ID for the od_url_metrics post which provided the URL Metrics in the collection.
 * @property-read array<string, mixed>           $normalized_query_vars       Normalized query vars.
 * @property-read non-empty-string               $url_metrics_slug            Slug for the od_url_metrics post.
 * @property-read OD_Link_Collection             $link_collection             Link collection.
 */
final class OD_Template_Optimization_Context {

	/**
	 * URL Metric group collection.
	 *
	 * @since 1.0.0
	 * @var OD_URL_Metric_Group_Collection
	 */
	private $url_metric_group_collection;

	/**
	 * ID for the od_url_metrics post which provided the URL Metrics in the collection.
	 *
	 * May be null if no post has been created yet.
	 *
	 * @since 1.0.0
	 * @var positive-int|null
	 */
	private $url_metrics_id;

	/**
	 * Normalized query vars.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	private $normalized_query_vars;

	/**
	 * Slug for the od_url_metrics post.
	 *
	 * @since 1.0.0
	 * @var non-empty-string
	 */
	private $url_metrics_slug;

	/**
	 * Link collection.
	 *
	 * @since 1.0.0
	 * @var OD_Link_Collection
	 */
	private $link_collection;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @access private
	 *
	 * @param OD_URL_Metric_Group_Collection $url_metric_group_collection URL Metric group collection.
	 * @param OD_Link_Collection             $link_collection             Link collection.
	 * @param array<string, mixed>           $normalized_query_vars       Normalized query vars.
	 * @param non-empty-string               $url_metrics_slug            Slug for the od_url_metrics post.
	 * @param positive-int|null              $url_metrics_id              ID for the od_url_metrics post which provided the URL Metrics in the collection. May be null if no post has been created yet.
	 */
	public function __construct( OD_URL_Metric_Group_Collection $url_metric_group_collection, OD_Link_Collection $link_collection, array $normalized_query_vars, string $url_metrics_slug, ?int $url_metrics_id ) {
		$this->url_metric_group_collection = $url_metric_group_collection;
		$this->link_collection             = $link_collection;
		$this->normalized_query_vars       = $normalized_query_vars;
		$this->url_metrics_slug            = $url_metrics_slug;
		$this->url_metrics_id              = $url_metrics_id;
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
		// Note: There is intentionally not a 'processor' case to expose $this->processor.
		switch ( $name ) {
			case 'url_metrics_id':
				return $this->url_metrics_id;
			case 'url_metric_group_collection':
				return $this->url_metric_group_collection;
			case 'normalized_query_vars':
				return $this->normalized_query_vars;
			case 'url_metrics_slug':
				return $this->url_metrics_slug;
			case 'link_collection':
				return $this->link_collection;
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
