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
 */
final class OD_Tag_Visitor_Context {

	/**
	 * HTML tag processor.
	 *
	 * @var OD_HTML_Tag_Processor
	 * @readonly
	 */
	public $processor;

	/**
	 * URL metrics group collection.
	 *
	 * @var OD_URL_Metrics_Group_Collection
	 * @readonly
	 */
	public $url_metrics_group_collection;

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
	 * @param OD_HTML_Tag_Processor           $processor                    HTML tag processor.
	 * @param OD_URL_Metrics_Group_Collection $url_metrics_group_collection URL metrics group collection.
	 * @param OD_Link_Collection              $link_collection              Link collection.
	 */
	public function __construct( OD_HTML_Tag_Processor $processor, OD_URL_Metrics_Group_Collection $url_metrics_group_collection, OD_Link_Collection $link_collection ) {
		$this->processor                    = $processor;
		$this->url_metrics_group_collection = $url_metrics_group_collection;
		$this->link_collection              = $link_collection;
	}
}
