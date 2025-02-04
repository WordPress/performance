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
 * @property-read OD_URL_Metric_Group_Collection $url_metrics_group_collection Deprecated property accessed via magic getter. Use the url_metric_group_collection property instead.
 */
final class OD_Tag_Visitor_Context {

	/**
	 * HTML tag processor.
	 *
	 * @since 0.4.0
	 * @var OD_HTML_Tag_Processor
	 * @readonly
	 */
	public $processor;

	/**
	 * URL Metric group collection.
	 *
	 * @since 0.4.0
	 * @var OD_URL_Metric_Group_Collection
	 * @readonly
	 */
	public $url_metric_group_collection;

	/**
	 * Link collection.
	 *
	 * @since 0.4.0
	 * @var OD_Link_Collection
	 * @readonly
	 */
	public $link_collection;

	/**
	 * Visited tag state.
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
	 */
	public function __construct( OD_HTML_Tag_Processor $processor, OD_URL_Metric_Group_Collection $url_metric_group_collection, OD_Link_Collection $link_collection, OD_Visited_Tag_State $visited_tag_state ) {
		$this->processor                   = $processor;
		$this->url_metric_group_collection = $url_metric_group_collection;
		$this->link_collection             = $link_collection;
		$this->visited_tag_state           = $visited_tag_state;
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
	 * Gets deprecated property.
	 *
	 * @since 0.7.0
	 * @todo Remove this when no plugins are possibly referring to the url_metrics_group_collection property anymore.
	 *
	 * @param string $name Property name.
	 * @return OD_URL_Metric_Group_Collection URL Metric group collection.
	 *
	 * @throws Error When property is unknown.
	 */
	public function __get( string $name ): OD_URL_Metric_Group_Collection {
		if ( 'url_metrics_group_collection' === $name ) {
			_doing_it_wrong(
				__CLASS__ . '::$url_metrics_group_collection',
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
		}
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
