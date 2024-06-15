<?php
/**
 * Optimization Detective extensions by Embed Optimizer.
 *
 * @since n.e.x.t
 * @package embed-optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers the tag visitor for embeds.
 *
 * @since n.e.x.t
 *
 * @param OD_Tag_Visitor_Registry $registry Tag visitor registry.
 */
function embed_optimizer_register_tag_visitors( OD_Tag_Visitor_Registry $registry ): void {
	$registry->register( 'embeds', 'embed_optimizer_visit_tag' );
}
add_action( 'od_register_tag_visitors', 'embed_optimizer_register_tag_visitors' );

/**
 * Visits a tag.
 *
 * @since n.e.x.t
 *
 * @param OD_HTML_Tag_Walker              $walker                       Walker.
 * @param OD_URL_Metrics_Group_Collection $url_metrics_group_collection URL metrics group collection.
 * @param OD_Link_Collection              $link_collection              Link collection.
 * @return bool Whether the visitor visited the tag.
 */
function embed_optimizer_visit_tag( OD_HTML_Tag_Walker $walker, OD_URL_Metrics_Group_Collection $url_metrics_group_collection, OD_Link_Collection $link_collection ): bool {
	if ( ! (
		'FIGURE' === $walker->get_tag()
		&&
		$walker->has_class( 'wp-block-embed' )
	) ) {
		return false;
	}

	$max_intersection_ratio = $url_metrics_group_collection->get_element_max_intersection_ratio( $walker->get_xpath() );

	if ( $max_intersection_ratio > 0 ) {

		// TODO: Add more cases.
		if ( $walker->has_class( 'wp-block-embed-youtube' ) ) {
			$link_collection->add_link(
				array(
					'rel'  => 'preconnect',
					'href' => 'https://i.ytimg.com',
				)
			);
			// TODO: Undo lazy-loading?
		}
	} else {
		$walker->set_meta_attribute( 'needs-lazy-loading', 'true' );
		// TODO: Add lazy-loading?
	}

	return true;
}
