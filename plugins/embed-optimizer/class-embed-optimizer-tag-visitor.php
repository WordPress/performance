<?php
/**
 * Embed Optimizer Prioritizer: Embed_Optimizer_Tag_Visitor class
 *
 * @package embed-optimizer
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Visitor for the tag walker that optimizes embeds.
 *
 * @since n.e.x.t
 * @access private
 */
final class Embed_Optimizer_Tag_Visitor extends Image_Prioritizer_Tag_Visitor {

	/**
	 * Visits a tag.
	 *
	 * @param OD_HTML_Tag_Walker $walker Walker.
	 * @return bool Whether the visitor visited the tag.
	 */
	public function __invoke( OD_HTML_Tag_Walker $walker ): bool {
		if ( ! (
			'FIGURE' === $walker->get_tag()
			&&
			$walker->has_class( 'wp-block-embed' )
		) ) {
			return false;
		}

		// TODO: Everything.

		return true;
	}
}
