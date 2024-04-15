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
	 * Whether the old (pre-WP 6.5) signature for WP_HTML_Text_Replacement is needed.
	 *
	 * WordPress 6.5 changed the $end arg in the WP_HTML_Text_Replacement constructor to $length.
	 *
	 * @var bool
	 */
	private $old_text_replacement_signature_needed;

	/**
	 * Constructor.
	 *
	 * @param string $html HTML to process.
	 */
	public function __construct( $html ) {
		$this->old_text_replacement_signature_needed = version_compare( get_bloginfo( 'version' ), '6.5', '<' );
		parent::__construct( $html );
	}

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
			$this->old_text_replacement_signature_needed ? $start : 0,
			$html
		);
		return true;
	}
}
