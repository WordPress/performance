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
	 * @since n.e.x.t
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

		$max_intersection_ratio = $this->url_metrics_group_collection->get_element_max_intersection_ratio( $processor->get_xpath() );

		if ( $max_intersection_ratio > 0 ) {
			/*
			 * The following embeds have been chosen for optimization due to their relative popularity among all embed types.
			 * See <https://colab.sandbox.google.com/drive/1nSpg3qoCLY-cBTV2zOUkgUCU7R7X2f_R?resourcekey=0-MgT7Ur0pT__vw-5_AHjgWQ#scrollTo=utZv59sXzXvS>.
			 * The list of hosts being preconnected to was obtained by inserting an embed into a post and then looking
			 * at the network log on the frontend as the embed renders. Each should include the host of the iframe src
			 * as well as URLs for assets used by the embed, _if_ the URL looks like it is not geotargeted (e.g. '-us')
			 * or load-balanced (e.g. 's0.example.com'). For the load balancing case, attempt to load the asset by
			 * incrementing the number appearing in the subdomain (e.g. s1.example.com). If the asset still loads, then
			 * it is a likely case of a load balancing domain name which cannot be safely preconnected since it could
			 * not end up being the load balanced domain used for the embed. Lastly, these domains are only for the URLs
			 * for GET requests, as POST requests are not likely to be part of the critical rendering path.
			 */
			$preconnect_hrefs = array();
			if ( $processor->has_class( 'wp-block-embed-youtube' ) ) {
				$preconnect_hrefs[] = 'https://www.youtube.com';
				$preconnect_hrefs[] = 'https://i.ytimg.com';
			} elseif ( $processor->has_class( 'wp-block-embed-twitter' ) ) {
				$preconnect_hrefs[] = 'https://syndication.twitter.com';
				$preconnect_hrefs[] = 'https://pbs.twimg.com';
			} elseif ( $processor->has_class( 'wp-block-embed-vimeo' ) ) {
				$preconnect_hrefs[] = 'https://player.vimeo.com';
				$preconnect_hrefs[] = 'https://f.vimeocdn.com';
				$preconnect_hrefs[] = 'https://i.vimeocdn.com';
			} elseif ( $processor->has_class( 'wp-block-embed-spotify' ) ) {
				$preconnect_hrefs[] = 'https://apresolve.spotify.com';
				$preconnect_hrefs[] = 'https://embed-cdn.spotifycdn.com';
				$preconnect_hrefs[] = 'https://encore.scdn.co';
				$preconnect_hrefs[] = 'https://i.scdn.co';
			} elseif ( $processor->has_class( 'wp-block-embed-videopress' ) || $processor->has_class( 'wp-block-embed-wordpress-tv' ) ) {
				$preconnect_hrefs[] = 'https://video.wordpress.com';
				$preconnect_hrefs[] = 'https://public-api.wordpress.com';
				$preconnect_hrefs[] = 'https://videos.files.wordpress.com';
				$preconnect_hrefs[] = 'https://v0.wordpress.com'; // This does not appear to be a load-balanced domain since v1.wordpress.com is not valid.
			} elseif ( $processor->has_class( 'wp-block-embed-instagram' ) ) {
				$preconnect_hrefs[] = 'https://www.instagram.com';
				$preconnect_hrefs[] = 'https://static.cdninstagram.com';
				$preconnect_hrefs[] = 'https://scontent.cdninstagram.com';
			} elseif ( $processor->has_class( 'wp-block-embed-tiktok' ) ) {
				$preconnect_hrefs[] = 'https://www.tiktok.com';
				// Note: The other domains used for TikTok embeds include https://lf16-tiktok-web.tiktokcdn-us.com,
				// https://lf16-cdn-tos.tiktokcdn-us.com, and https://lf16-tiktok-common.tiktokcdn-us.com among others
				// which either appear to be geo-targeted ('-us') _or_ load-balanced ('lf16'). So these are not added
				// to the preconnected hosts.
			} elseif ( $processor->has_class( 'wp-block-embed-amazon' ) ) {
				$preconnect_hrefs[] = 'https://read.amazon.com';
				$preconnect_hrefs[] = 'https://m.media-amazon.com';
			} elseif ( $processor->has_class( 'wp-block-embed-soundcloud' ) ) {
				$preconnect_hrefs[] = 'https://w.soundcloud.com';
				$preconnect_hrefs[] = 'https://widget.sndcdn.com';
				// Note: There is also https://i1.sndcdn.com which is for the album art, but the '1' indicates it may be geotargeted/load-balanced.
			} elseif ( $processor->has_class( 'wp-block-embed-pinterest' ) ) {
				$preconnect_hrefs[] = 'https://assets.pinterest.com';
				$preconnect_hrefs[] = 'https://widgets.pinterest.com';
				$preconnect_hrefs[] = 'https://i.pinimg.com';
			}

			foreach ( $preconnect_hrefs as $preconnect_href ) {
				$this->link_collection->add_link(
					array(
						'rel'  => 'preconnect',
						'href' => $preconnect_href,
					)
				);
			}
		} elseif ( embed_optimizer_update_markup( $processor, false ) && ! $this->added_lazy_script ) {
			$processor->append_body_html( wp_get_inline_script_tag( embed_optimizer_get_lazy_load_script(), array( 'type' => 'module' ) ) );
			$this->added_lazy_script = true;
		}

		return true;
	}
}
