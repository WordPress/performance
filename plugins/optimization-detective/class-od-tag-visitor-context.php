<?php
/**
 * Optimization Detective: OD_Tag_Visitor_Context class
 *
 * @package optimization-detective
 * @since 0.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Context for tag visitors invoked for each tag while walking over a document.
 *
 * @since 0.4.0
 * @access private
 *
 * @property-read OD_URL_Metric_Group_Collection $url_metrics_group_collection Deprecated property accessed via magic getter. Use the url_metric_group_collection property instead.
 */
final class OD_Tag_Visitor_Context {

	/**
	 * HTML (tag) processor.
	 *
	 * @var OD_HTML_Tag_Processor|OD_HTML_Processor
	 * @readonly
	 */
	public $processor;

	/**
	 * URL metric group collection.
	 *
	 * @var OD_URL_Metric_Group_Collection
	 * @readonly
	 */
	public $url_metric_group_collection;

	/**
	 * Link collection.
	 *
	 * @var OD_Link_Collection
	 * @readonly
	 */
	public $link_collection;

	/**
	 * Constructor.
	 *
	 * @param OD_HTML_Tag_Processor|OD_HTML_Processor $processor                  HTML tag processor.
	 * @param OD_URL_Metric_Group_Collection          $url_metric_group_collection URL metric group collection.
	 * @param OD_Link_Collection                      $link_collection            Link collection.
	 */
	public function __construct( $processor, OD_URL_Metric_Group_Collection $url_metric_group_collection, OD_Link_Collection $link_collection ) {
		$this->processor                   = $processor;
		$this->url_metric_group_collection = $url_metric_group_collection;
		$this->link_collection             = $link_collection;
	}

	/**
	 * Gets deprecated property.
	 *
	 * @since 0.7.0
	 * @todo Remove this when no plugins are possibly referring to the url_metrics_group_collection property anymore.
	 *
	 * @param string $name Property name.
	 * @return OD_URL_Metric_Group_Collection URL metric group collection.
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
