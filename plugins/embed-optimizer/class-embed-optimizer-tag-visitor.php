<?php
/**
 * Tag visitor for Embed Optimizer.
 *
 * @package embed-optimizer
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tag visitor that optimizes embeds.
 *
 * @since 0.1.0
 * @access private
 */
final class Embed_Optimizer_Tag_Visitor {

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
	 * Whether the lazy-loading script was added to the body.
	 *
	 * @var bool
	 */
	protected $added_lazy_script = false;

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
	 * @return bool Whether the visit or visited the tag.
	 */
	public function __invoke( OD_HTML_Tag_Processor $processor ): bool {
		if ( ! (
			'FIGURE' === $processor->get_tag()
			&&
			$processor->has_class( 'wp-block-embed' )
		) ) {
			return false;
		}

		$original_bookmarks = $processor->get_bookmark_names();

		$max_intersection_ratio = $this->url_metrics_group_collection->get_element_max_intersection_ratio( $processor->get_xpath() );

		if ( $max_intersection_ratio > 0 ) {

			// TODO: Add more cases.
			if ( $processor->has_class( 'wp-block-embed-youtube' ) ) {
				$this->link_collection->add_link(
					array(
						'rel'  => 'preconnect',
						'href' => 'https://i.ytimg.com',
					)
				);
			}
		} elseif ( embed_optimizer_update_markup( $processor ) && ! $this->added_lazy_script ) {
			$processor->append_body_html( '<!-- TODO: Add lazy-loading script. -->' ); // TODO: This does not work because the end of the BODY hasn't been encountered yet. We need to rather hold onto a buffer of the content to append.
			$this->added_lazy_script = true;
		}

		// Since there is a limit to the number of bookmarks we can add, make sure any new ones we add get removed.
		// TODO: Instead of this consider throwing an exception inside embed_optimizer_update_markup() and clear out the bookmarks in the catch() block or else when the function returns.
		$new_bookmarks = array_diff( $original_bookmarks, $processor->get_bookmark_names() );
		foreach ( $new_bookmarks as $new_bookmark ) {
			$processor->release_bookmark( $new_bookmark );
		}

		return true;
	}
}
