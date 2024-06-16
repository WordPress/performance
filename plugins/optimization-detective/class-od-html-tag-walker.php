<?php
/**
 * Optimization Detective: OD_HTML_Tag_Walker class
 *
 * @package optimization-detective
 * @since 0.1.1
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walker leveraging WP_HTML_Tag_Processor which gathers breadcrumbs for computing XPaths while iterating the open_tags() generator.
 *
 * Eventually this class should be made largely obsolete once `WP_HTML_Processor` is fully implemented to support all HTML tags.
 *
 * @since 0.1.0
 * @since 0.1.1 Renamed from OD_HTML_Tag_Processor to OD_HTML_Tag_Walker
 * @access private
 */
final class OD_HTML_Tag_Walker {

	/**
	 * Processor.
	 *
	 * @var OD_HTML_Tag_Processor
	 */
	private $processor;

	/**
	 * Whether walking has started.
	 *
	 * @var bool
	 */
	private $did_start_walking = false;

	/**
	 * Constructor.
	 *
	 * @param string $html HTML to process.
	 */
	public function __construct( string $html ) {
		$this->processor = new OD_HTML_Tag_Processor( $html );
	}

	/**
	 * Gets all open tags in the document.
	 *
	 * A generator is used so that when iterating at a specific tag, additional information about the tag at that point
	 * can be queried from the class. Similarly, mutations may be performed when iterating at an open tag.
	 *
	 * @since 0.1.0
	 *
	 * @return Generator<string> Tag name of current open tag.
	 *
	 * @throws Exception When walking has already started.
	 */
	public function open_tags(): Generator {
		if ( $this->did_start_walking ) {
			throw new Exception( esc_html__( 'Open tags may only be iterated over once per instance.', 'optimization-detective' ) );
		}
		$this->did_start_walking = true;

		while ( $this->processor->next_tag() ) {
			$tag_name = $this->processor->get_tag();
			if ( ! is_string( $tag_name ) ) {
				continue;
			}
			if ( ! $this->processor->is_tag_closer() ) {
				yield $tag_name;
			}
		}
	}

	/**
	 * Gets XPath for the current open tag.
	 *
	 * It would be nicer if this were like `/html[1]/body[2]` but in XPath the position() here refers to the
	 * index of the preceding node set. So it has to rather be written `/*[1][self::html]/*[2][self::body]`.
	 *
	 * @since 0.1.0
	 *
	 * @return string XPath.
	 */
	public function get_xpath(): string {
		return $this->processor->get_xpath();
	}

	/**
	 * Append HTML to the HEAD.
	 *
	 * Before this can be called, the document must first have been iterated over with
	 * {@see OD_HTML_Tag_Walker::open_tags()} so that the bookmark for the HEAD end tag is set.
	 *
	 * @param string $html HTML to inject.
	 * @return bool Whether successful.
	 */
	public function append_head_html( string $html ): bool {
		return $this->processor->append_head_html( $html );
	}

	/**
	 * Append HTML to the BODY.
	 *
	 * Before this can be called, the document must first have been iterated over with
	 * {@see OD_HTML_Tag_Walker::open_tags()} so that the bookmark for the BODY end tag is set.
	 *
	 * @param string $html HTML to inject.
	 * @return bool Whether successful.
	 */
	public function append_body_html( string $html ): bool {
		return $this->processor->append_body_html( $html );
	}

	/**
	 * Returns the uppercase name of the matched tag.
	 *
	 * This is a wrapper around the underlying WP_HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @since 0.3.0
	 * @see WP_HTML_Tag_Processor::get_tag()
	 *
	 * @return string|null Name of currently matched tag in input HTML, or `null` if none found.
	 */
	public function get_tag(): ?string {
		return $this->processor->get_tag();
	}

	/**
	 * Returns the value of a requested attribute from a matched tag opener if that attribute exists.
	 *
	 * This is a wrapper around the underlying WP_HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @since 0.1.0
	 * @see WP_HTML_Tag_Processor::get_attribute()
	 *
	 * @param string $name Name of attribute whose value is requested.
	 * @return string|true|null Value of attribute or `null` if not available. Boolean attributes return `true`.
	 */
	public function get_attribute( string $name ) {
		return $this->processor->get_attribute( $name );
	}

	/**
	 * Updates or creates a new attribute on the currently matched tag with the passed value.
	 *
	 * This is a wrapper around the underlying WP_HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @since 0.1.0
	 * @see WP_HTML_Tag_Processor::set_attribute()
	 *
	 * @param string      $name  The attribute name to target.
	 * @param string|bool $value The new attribute value.
	 * @return bool Whether an attribute value was set.
	 */
	public function set_attribute( string $name, $value ): bool {
		$existing_value = $this->processor->get_attribute( $name );
		$result         = $this->processor->set_attribute( $name, $value );
		if ( $result ) {
			if ( is_string( $existing_value ) ) {
				$this->set_meta_attribute( "replaced-{$name}", $existing_value );
			} else {
				$this->set_meta_attribute( "added-{$name}", true );
			}
		}
		return $result;
	}

	/**
	 * Sets a meta attribute.
	 *
	 * All meta attributes are prefixed with 'data-od-'.
	 *
	 * @param string      $name  Meta attribute name.
	 * @param string|true $value Value.
	 * @return bool Whether an attribute was set.
	 */
	public function set_meta_attribute( string $name, $value ): bool {
		return $this->processor->set_attribute( "data-od-{$name}", $value );
	}

	/**
	 * Removes an attribute from the currently-matched tag.
	 *
	 * This is a wrapper around the underlying WP_HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @since 0.1.0
	 * @see WP_HTML_Tag_Processor::remove_attribute()
	 *
	 * @param string $name The attribute name to remove.
	 * @return bool Whether an attribute was removed.
	 */
	public function remove_attribute( string $name ): bool {
		$old_value = $this->processor->get_attribute( $name );
		$result    = $this->processor->remove_attribute( $name );
		if ( $result ) {
			$this->set_meta_attribute( "removed-{$name}", is_string( $old_value ) ? $old_value : true );
		}
		return $result;
	}

	/**
	 * Returns if a matched tag contains the given ASCII case-insensitive class name.
	 *
	 * This is a wrapper around the underlying WP_HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @since n.e.x.t
	 * @see   WP_HTML_Tag_Processor::has_class()
	 *
	 * @param string $wanted_class Look for this CSS class name, ASCII case-insensitive.
	 * @return bool|null Whether the matched tag contains the given class name, or null if not matched.
	 */
	public function has_class( string $wanted_class ): ?bool {
		return $this->processor->has_class( $wanted_class );
	}

	/**
	 * Returns the string representation of the HTML Tag Processor.
	 *
	 * This is a wrapper around the underlying HTML_Tag_Processor method of the same name since only a limited number of
	 * methods can be exposed to prevent moving the pointer in such a way as the breadcrumb calculation is invalidated.
	 *
	 * @since 0.1.0
	 * @see WP_HTML_Tag_Processor::get_updated_html()
	 *
	 * @return string The processed HTML.
	 */
	public function get_updated_html(): string {
		return $this->processor->get_updated_html();
	}
}
