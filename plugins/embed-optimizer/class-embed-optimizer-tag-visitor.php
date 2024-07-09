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
 * @since n.e.x.t
 * @access private
 */
final class Embed_Optimizer_Tag_Visitor {

	/**
	 * Whether the lazy-loading script was added to the body.
	 *
	 * @var bool
	 */
	protected $added_lazy_script = false;

	/**
	 * Visits a tag.
	 *
	 * @since n.e.x.t
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 * @return bool Whether the visit or visited the tag.
	 */
	public function __invoke( OD_Tag_Visitor_Context $context ): bool {
		$processor = $context->processor;
		if ( ! (
			'FIGURE' === $processor->get_tag()
			&&
			$processor->has_class( 'wp-block-embed' )
		) ) {
			return false;
		}

		$max_intersection_ratio = $context->url_metrics_group_collection->get_element_max_intersection_ratio( $processor->get_xpath() );

		if ( $max_intersection_ratio > 0 ) {

			$preconnect_hrefs = array();
			// TODO: Add more cases.
			if ( $processor->has_class( 'wp-block-embed-youtube' ) ) {
				$preconnect_hrefs[] = 'https://www.youtube.com';
				$preconnect_hrefs[] = 'https://i.ytimg.com';
			} elseif ( $processor->has_class( 'wp-block-embed-twitter' ) ) {
				$preconnect_hrefs[] = 'https://syndication.twitter.com';
				$preconnect_hrefs[] = 'https://pbs.twimg.com';
			} elseif ( $processor->has_class( 'wp-block-embed-wordpress-tv' ) ) {
				$preconnect_hrefs[] = 'https://video.wordpress.com';
				$preconnect_hrefs[] = 'https://public-api.wordpress.com';
				$preconnect_hrefs[] = 'https://videos.files.wordpress.com';
			}

			foreach ( $preconnect_hrefs as $preconnect_href ) {
				$context->link_collection->add_link(
					array(
						'rel'  => 'preconnect',
						'href' => $preconnect_href,
					)
				);
			}
		} elseif ( embed_optimizer_update_markup( $processor ) && ! $this->added_lazy_script ) {
			$processor->append_body_html( wp_get_inline_script_tag( embed_optimizer_get_lazy_load_script(), array( 'type' => 'module' ) ) );
			$this->added_lazy_script = true;
		}

		return true;
	}
}
