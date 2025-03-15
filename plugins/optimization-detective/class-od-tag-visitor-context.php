<?php
/**
 * Optimization Detective: OD_Tag_Visitor_Context class
 *
 * @package optimization-detective
 * @since 0.4.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Context for tag visitors invoked for each tag while walking over a document.
 *
 * @since 0.4.0
 *
 * @property-read OD_HTML_Tag_Processor          $processor                    HTML tag processor.
 * @property-read OD_URL_Metric_Group_Collection $url_metric_group_collection  URL Metric group collection.
 * @property-read OD_Link_Collection             $link_collection              Link collection.
 * @property-read positive-int|null              $url_metrics_id               ID for the od_url_metrics post which provided the URL Metrics in the collection.
 * @property-read OD_URL_Metric_Group_Collection $url_metrics_group_collection Deprecated alias for the $url_metric_group_collection property.
 */
final class OD_Tag_Visitor_Context {

	/**
	 * HTML tag processor.
	 *
	 * @since 0.4.0
	 * @var OD_HTML_Tag_Processor
	 */
	private $processor;

	/**
	 * URL Metric group collection.
	 *
	 * @since 0.4.0
	 * @var OD_URL_Metric_Group_Collection
	 */
	private $url_metric_group_collection;

	/**
	 * Link collection.
	 *
	 * @since 0.4.0
	 * @var OD_Link_Collection
	 */
	private $link_collection;

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
	 * Visited tag state.
	 *
	 * Important: This object is not exposed directly by the getter. It is only exposed via {@see self::track_tag()}.
	 *
	 * @since 1.0.0
	 * @var OD_Visited_Tag_State
	 */
	private $visited_tag_state;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param OD_HTML_Tag_Processor          $processor                   HTML tag processor.
	 * @param OD_URL_Metric_Group_Collection $url_metric_group_collection URL Metric group collection.
	 * @param OD_Link_Collection             $link_collection             Link collection.
	 * @param OD_Visited_Tag_State           $visited_tag_state           Visited tag state.
	 * @param positive-int|null              $url_metrics_id              ID for the od_url_metrics post which provided the URL Metrics in the collection. May be null if no post has been created yet.
	 */
	public function __construct( OD_HTML_Tag_Processor $processor, OD_URL_Metric_Group_Collection $url_metric_group_collection, OD_Link_Collection $link_collection, OD_Visited_Tag_State $visited_tag_state, ?int $url_metrics_id ) {
		$this->processor                   = $processor;
		$this->url_metric_group_collection = $url_metric_group_collection;
		$this->link_collection             = $link_collection;
		$this->visited_tag_state           = $visited_tag_state;
		$this->url_metrics_id              = $url_metrics_id;
	}

	/**
	 * Marks the tag for being tracked in URL Metrics.
	 *
	 * Calling this method from a tag visitor has the same effect as a tag visitor returning `true`.
	 *
	 * @since 1.0.0
	 */
	public function track_tag(): void {
		$this->visited_tag_state->track_tag();
	}

	/**
	 * Gets a property.
	 *
	 * @since 0.7.0
	 *
	 * @param string $name Property name.
	 * @return mixed Property value.
	 *
	 * @throws Error When property is unknown.
	 */
	public function __get( string $name ) {
		// Note: There is intentionally not a 'visited_tag_state' case to expose $this->visited_tag_state.
		switch ( $name ) {
			case 'processor':
				return $this->processor;
			case 'link_collection':
				return $this->link_collection;
			case 'url_metrics_id':
				return $this->url_metrics_id;
			case 'url_metric_group_collection':
				return $this->url_metric_group_collection;
			case 'url_metrics_group_collection':
				// TODO: Remove this when no plugins are possibly referring to the url_metrics_group_collection property anymore.
				_doing_it_wrong(
					esc_html( __CLASS__ . '::$' . $name ),
					esc_html(
						sprintf(
							/* translators: %s is class member variable name */
							__( 'Use %s instead.', 'optimization-detective' ),
							__CLASS__ . '::$url_metric_group_collection'
						)
					),
					'optimization-detective 0.7.0'
				);
				return $this->url_metric_group_collection;
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
