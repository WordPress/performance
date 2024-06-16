<?php
/**
 * Image Prioritizer: IP_Image_Tag_Visitor class
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag visitor that optimizes image tags.
 *
 * @since 0.1.0
 * @access private
 */
abstract class Image_Prioritizer_Tag_Visitor {

	/**
	 * URL Metrics Group Collection.
	 *
	 * @var OD_URL_Metrics_Group_Collection
	 */
	protected $url_metrics_group_collection;

	/**
	 * Link Collection.
	 *
	 * @var OD_Link_Collection
	 */
	protected $link_collection;

	/**
	 * Constructor.
	 *
	 * @param OD_URL_Metrics_Group_Collection $url_metrics_group_collection URL Metrics Group Collection.
	 * @param OD_Link_Collection              $link_collection              Link Collection.
	 */
	public function __construct( OD_URL_Metrics_Group_Collection $url_metrics_group_collection, OD_Link_Collection $link_collection ) {
		$this->url_metrics_group_collection = $url_metrics_group_collection;
		$this->link_collection              = $link_collection;
	}

	/**
	 * Visits a tag.
	 *
	 * @param OD_HTML_Tag_Processor $processor Processor.
	 * @return bool Whether the visitor visited the tag.
	 */
	abstract public function __invoke( OD_HTML_Tag_Processor $processor ): bool;

	/**
	 * Determines if the provided URL is a data: URL.
	 *
	 * @param string $url URL.
	 * @return bool Whether data URL.
	 */
	protected function is_data_url( string $url ): bool {
		return str_starts_with( strtolower( $url ), 'data:' );
	}
}
