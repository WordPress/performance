<?php
/**
 * Optimization Detective: OD_HTML_Tag_Processor class
 *
 * @package optimization-detective
 * @since 0.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Extension to WP_HTML_Tag_Processor that supports injecting HTML.
 *
 * @since 0.1.1
 * @access private
 */
final class OD_HTML_Tag_Processor extends WP_HTML_Tag_Processor {

	/**
	 * Appends HTML to the provided bookmark.
	 *
	 * @param string $bookmark Bookmark.
	 * @param string $html     HTML to inject.
	 * @return bool Whether the HTML was appended.
	 */
	public function append_html( string $bookmark, string $html ): bool {
		if ( ! $this->has_bookmark( $bookmark ) ) {
			return false;
		}

		$start = $this->bookmarks[ $bookmark ]->start;

		$this->lexical_updates[] = new WP_HTML_Text_Replacement(
			$start,
			0,
			$html
		);
		return true;
	}
}
