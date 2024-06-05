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
 * Visitor for the tag walker that optimizes image tags.
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
	 * Preload Link Collection.
	 *
	 * @var OD_Preload_Link_Collection
	 */
	protected $preload_links_collection;

	/**
	 * Constructor.
	 *
	 * @param OD_URL_Metrics_Group_Collection $url_metrics_group_collection URL Metrics Group Collection.
	 * @param OD_Preload_Link_Collection      $preload_links_collection     Preload Link Collection.
	 */
	public function __construct( OD_URL_Metrics_Group_Collection $url_metrics_group_collection, OD_Preload_Link_Collection $preload_links_collection ) {
		$this->url_metrics_group_collection = $url_metrics_group_collection;
		$this->preload_links_collection     = $preload_links_collection;
	}

	/**
	 * Visits a tag.
	 *
	 * @param OD_HTML_Tag_Walker $walker Walker.
	 * @return bool Whether the visitor visited the tag.
	 */
	abstract public function __invoke( OD_HTML_Tag_Walker $walker ): bool;

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
