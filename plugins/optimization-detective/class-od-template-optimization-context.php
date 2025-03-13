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
 * Context for optimizing a template.
 *
 * @since n.e.x.t
 *
 * @property-read OD_URL_Metric_Group_Collection $url_metric_group_collection URL Metric group collection.
 * @property-read OD_Tag_Visitor_Registry        $tag_visitor_registry        Tag visitor registry.
 * @property-read positive-int|null              $url_metrics_id              ID for the od_url_metrics post which provided the URL Metrics in the collection.
 * @property-read array<string, mixed>           $normalized_query_vars       Normalized query vars.
 * @property-read non-empty-string               $url_metrics_slug            Slug for the od_url_metrics post.
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
	 * HTML Tag Processor.
	 *
	 * This object is not directly exposed with an accessor property. This class exposes {@see self::append_head_html()}
	 * and {@see self::append_body_html()} methods which wrap calls to the underlying
	 * {@see OD_HTML_Tag_Processor::append_head_html()} and {@see OD_HTML_Tag_Processor::append_body_html()}.s
	 *
	 * @since n.e.x.t
	 * @var OD_HTML_Tag_Processor
	 */
	private $processor;

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
	 * @param OD_HTML_Tag_Processor          $processor                   HTML Tag Processor.
	 * @param OD_URL_Metric_Group_Collection $url_metric_group_collection URL Metric group collection.
	 * @param OD_Link_Collection             $link_collection             Link collection.
	 * @param array<string, mixed>           $normalized_query_vars       Normalized query vars.
	 * @param non-empty-string               $url_metrics_slug            Slug for the od_url_metrics post.
	 * @param positive-int|null              $url_metrics_id              ID for the od_url_metrics post which provided the URL Metrics in the collection. May be null if no post has been created yet.
	 */
	public function __construct( OD_HTML_Tag_Processor $processor, OD_URL_Metric_Group_Collection $url_metric_group_collection, OD_Link_Collection $link_collection, array $normalized_query_vars, string $url_metrics_slug, ?int $url_metrics_id ) {
		$this->processor                   = $processor;
		$this->url_metric_group_collection = $url_metric_group_collection;
		$this->link_collection             = $link_collection;
		$this->normalized_query_vars       = $normalized_query_vars;
		$this->url_metrics_slug            = $url_metrics_slug;
		$this->url_metrics_id              = $url_metrics_id;
	}

	/**
	 * Append HTML to the HEAD.
	 *
	 * The provided HTML must be valid! No validation is performed.
	 *
	 * @since n.e.x.t
	 *
	 * @param non-empty-string $html HTML to inject.
	 */
	public function append_head_html( string $html ): void {
		$this->processor->append_head_html( $html );
	}

	/**
	 * Append HTML to the BODY.
	 *
	 * The provided HTML must be valid! No validation is performed.
	 *
	 * @since n.e.x.t
	 *
	 * @param non-empty-string $html HTML to inject.
	 */
	public function append_body_html( string $html ): void {
		$this->processor->append_body_html( $html );
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
		// Note: The $processor is intentionally not exposed.
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
