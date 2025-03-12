<?php
/**
 * Optimization Detective: OD_Template_Optimization_Context class
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Context for optimizing a template prior to rendering.
 *
 * @since n.e.x.t
 *
 * @property-read OD_URL_Metric_Group_Collection $url_metric_group_collection URL Metric group collection.
 * @property-read OD_Tag_Visitor_Registry        $tag_visitor_registry        Tag visitor registry.
 * @property-read positive-int|null              $url_metrics_id              ID for the od_url_metrics post which provided the URL Metrics in the collection.
 * @property-read array<string, mixed>           $normalized_query_vars       Normalized query vars.
 * @property-read non-empty-string               $url_metrics_slug            Slug for the od_url_metrics post.
 * @property-read non-empty-string               $current_etag                Current ETag.
 * @property-read OD_Link_Collection             $link_collection             Link collection.
 */
final class OD_Template_Optimization_Context {

	/**
	 * URL Metric group collection.
	 *
	 * @since n.e.x.t
	 * @var OD_URL_Metric_Group_Collection
	 */
	private $url_metric_group_collection;

	/**
	 * Tag visitor registry.
	 *
	 * @since n.e.x.t
	 * @var OD_Tag_Visitor_Registry
	 */
	private $tag_visitor_registry;

	/**
	 * ID for the od_url_metrics post which provided the URL Metrics in the collection.
	 *
	 * May be null if no post has been created yet.
	 *
	 * @since n.e.x.t
	 * @var positive-int|null
	 */
	private $url_metrics_id;

	/**
	 * Normalized query vars.
	 *
	 * @since n.e.x.t
	 * @var array<string, mixed>
	 */
	private $normalized_query_vars;

	/**
	 * Slug for the od_url_metrics post.
	 *
	 * @since n.e.x.t
	 * @var non-empty-string
	 */
	private $url_metrics_slug;

	/**
	 * Current ETag.
	 *
	 * @since n.e.x.t
	 * @var non-empty-string
	 */
	private $current_etag;

	/**
	 * Link collection.
	 *
	 * @since n.e.x.t
	 * @var OD_Link_Collection
	 */
	private $link_collection;

	/**
	 * Constructor.
	 *
	 * @since n.e.x.t
	 *
	 * @param OD_URL_Metric_Group_Collection $url_metric_group_collection URL Metric group collection.
	 * @param OD_Tag_Visitor_Registry        $tag_visitor_registry        Tag visitor registry.
	 * @param positive-int|null              $url_metrics_id              ID for the od_url_metrics post which provided the URL Metrics in the collection. May be null if no post has been created yet.
	 * @param array<string, mixed>           $normalized_query_vars       Normalized query vars.
	 * @param non-empty-string               $url_metrics_slug            Slug for the od_url_metrics post.
	 * @param non-empty-string               $current_etag                Current ETag.
	 * @param OD_Link_Collection             $link_collection             Link collection.
	 */
	public function __construct( OD_URL_Metric_Group_Collection $url_metric_group_collection, OD_Tag_Visitor_Registry $tag_visitor_registry, ?int $url_metrics_id, array $normalized_query_vars, string $url_metrics_slug, string $current_etag, OD_Link_Collection $link_collection ) {
		$this->url_metric_group_collection = $url_metric_group_collection;
		$this->tag_visitor_registry        = $tag_visitor_registry;
		$this->url_metrics_id              = $url_metrics_id;
		$this->normalized_query_vars       = $normalized_query_vars;
		$this->url_metrics_slug            = $url_metrics_slug;
		$this->current_etag                = $current_etag;
		$this->link_collection             = $link_collection;
	}

	/**
	 * Gets a property.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $name Property name.
	 * @return mixed Property value.
	 *
	 * @throws Error When property is unknown.
	 */
	public function __get( string $name ) {
		switch ( $name ) {
			case 'tag_visitor_registry':
				return $this->tag_visitor_registry;
			case 'url_metrics_id':
				return $this->url_metrics_id;
			case 'url_metric_group_collection':
				return $this->url_metric_group_collection;
			case 'normalized_query_vars':
				return $this->normalized_query_vars;
			case 'url_metrics_slug':
				return $this->url_metrics_slug;
			case 'current_etag':
				return $this->current_etag;
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
