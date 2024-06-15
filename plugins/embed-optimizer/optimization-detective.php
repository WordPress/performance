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
 * @param OD_Tag_Visitor_Registry         $registry                     Tag visitor registry.
 * @param OD_URL_Metrics_Group_Collection $url_metrics_group_collection URL Metrics Group Collection.
 * @param OD_Link_Collection              $link_collection              Link Collection.
 */
function embed_optimizer_register_tag_visitors( OD_Tag_Visitor_Registry $registry, OD_URL_Metrics_Group_Collection $url_metrics_group_collection, OD_Link_Collection $link_collection ): void {
	require_once __DIR__ . '/class-embed-optimizer-tag-visitor.php';
	$registry->register( 'embeds', new Embed_Optimizer_Tag_Visitor( $url_metrics_group_collection, $link_collection ) );
}
add_action( 'od_register_tag_visitors', 'embed_optimizer_register_tag_visitors', 10, 3 );
