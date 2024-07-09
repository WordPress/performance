<?php
/**
 * Optimization Detective: OD_Preload_Link_Collection class
 *
 * @package optimization-detective
 * @since 0.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Collection for preload links added to the document.
 *
 * @phpstan-type Link array{
 *                   attributes: LinkAttributes,
 *                   minimum_viewport_width: int<0, max>|null,
 *                   maximum_viewport_width: positive-int|null
 *               }
 *
 * @phpstan-type LinkAttributes array{
 *                   href?: non-empty-string,
 *                   imagesrcset?: non-empty-string,
 *                   imagesizes?: non-empty-string,
 *                   crossorigin?: ''|'anonymous'|'use-credentials',
 *                   fetchpriority?: 'high'|'low'|'auto',
 *                   as: 'audio'|'document'|'embed'|'fetch'|'font'|'image'|'object'|'script'|'style'|'track'|'video'|'worker'
 *               }
 *
 * @since 0.3.0
 * @access private
 */
final class OD_Preload_Link_Collection implements Countable {

	/**
	 * Links.
	 *
	 * @var array<int, Link>
	 */
	private $links = array();

	/**
	 * Adds link.
	 *
	 * @phpstan-param LinkAttributes $attributes
	 *
	 * @param array             $attributes             Attributes.
	 * @param int<0, max>|null  $minimum_viewport_width Minimum width or null if not bounded or relevant.
	 * @param positive-int|null $maximum_viewport_width Maximum width or null if not bounded (i.e. infinity) or relevant.
	 *
	 * @throws InvalidArgumentException When invalid arguments are provided.
	 */
	public function add_link( array $attributes, ?int $minimum_viewport_width, ?int $maximum_viewport_width ): void {
		if ( ! array_key_exists( 'href', $attributes ) && ! array_key_exists( 'imagesrcset', $attributes ) ) {
			throw new InvalidArgumentException( esc_html__( 'Either the href or imagesrcset attributes must be supplied.', 'optimization-detective' ) );
		}
		if ( null !== $minimum_viewport_width && $minimum_viewport_width < 0 ) {
			throw new InvalidArgumentException( esc_html__( 'Minimum width must be at least zero.', 'optimization-detective' ) );
		}
		if ( null !== $maximum_viewport_width && ( $maximum_viewport_width < $minimum_viewport_width || $maximum_viewport_width < 0 ) ) {
			throw new InvalidArgumentException( esc_html__( 'Maximum width must be greater than zero and greater than the minimum width.', 'optimization-detective' ) );
		}
		foreach ( array( 'href', 'imagesrcset', 'imagesizes', 'crossorigin', 'fetchpriority', 'as' ) as $attribute_name ) {
			if ( array_key_exists( $attribute_name, $attributes ) && ! is_string( $attributes[ $attribute_name ] ) ) {
				throw new InvalidArgumentException( esc_html__( 'Link attributes must be strings.', 'optimization-detective' ) );
			}
		}

		$this->links[] = array(
			'attributes'             => $attributes,
			'minimum_viewport_width' => $minimum_viewport_width,
			'maximum_viewport_width' => $maximum_viewport_width,
		);
	}

	/**
	 * Prepares links by deduplicating adjacent links and adding media attributes.
	 *
	 * When two links are identical except for their minimum/maximum widths which are also consecutive, then merge them
	 * together. Also, add media attributes to the links.
	 *
	 * @return array<int, Link> Prepared links with adjacent-duplicates merged together and media attributes added.
	 */
	private function get_prepared_links(): array {
		$links = $this->links;

		usort(
			$links,
			/**
			 * Comparator.
			 *
			 * @param Link $a First link.
			 * @param Link $b Second link.
			 * @return int Comparison result.
			 */
			static function ( array $a, array $b ): int {
				return $a['minimum_viewport_width'] <=> $b['minimum_viewport_width'];
			}
		);

		// Deduplicate adjacent links.
		$prepared_links = array_reduce(
			$links,
			/**
			 * Reducer.
			 *
			 * @param array<int, Link> $carry Carry.
			 * @param Link $link Link.
			 * @return non-empty-array<int, Link> Potentially-reduced links.
			 */
			static function ( array $carry, array $link ): array {
				/**
				 * Last link.
				 *
				 * @var Link $last_link
				 */
				$last_link = end( $carry );
				if (
					$last_link
					&&
					$last_link['attributes'] === $link['attributes']
					&&
					$last_link['maximum_viewport_width'] + 1 === $link['minimum_viewport_width']
				) {
					$last_link['maximum_viewport_width'] = max( $last_link['maximum_viewport_width'], $link['maximum_viewport_width'] );

					// Update the last link with the new maximum viewport width.
					$carry[ count( $carry ) - 1 ] = $last_link;
				} else {
					$carry[] = $link;
				}
				return $carry;
			},
			array()
		);

		// Add media attributes to the deduplicated links.
		return array_map(
			static function ( array $link ): array {
				$media_attributes = array( 'screen' );
				if ( null !== $link['minimum_viewport_width'] && $link['minimum_viewport_width'] > 0 ) {
					$media_attributes[] = sprintf( '(min-width: %dpx)', $link['minimum_viewport_width'] );
				}
				if ( null !== $link['maximum_viewport_width'] && PHP_INT_MAX !== $link['maximum_viewport_width'] ) {
					$media_attributes[] = sprintf( '(max-width: %dpx)', $link['maximum_viewport_width'] );
				}
				$link['attributes']['media'] = implode( ' and ', $media_attributes );
				return $link;
			},
			$prepared_links
		);
	}

	/**
	 * Gets the HTML for the link tags.
	 *
	 * @return string Link tags HTML.
	 */
	public function get_html(): string {
		$link_tags = array();

		foreach ( $this->get_prepared_links() as $link ) {
			$link_tag = '<link data-od-added-tag rel="preload"';
			foreach ( $link['attributes'] as $name => $value ) {
				$link_tag .= sprintf( ' %s="%s"', $name, esc_attr( $value ) );
			}
			$link_tag .= ">\n";

			$link_tags[] = $link_tag;
		}

		return implode( '', $link_tags );
	}

	/**
	 * Constructs the Link HTTP response header.
	 *
	 * @return string|null Link HTTP response header, or null if there are none.
	 */
	public function get_response_header(): ?string {
		$link_headers = array();

		foreach ( $this->get_prepared_links() as $link ) {
			// The about:blank is present since a Link without a reference-uri is invalid so any imagesrcset would otherwise not get downloaded.
			$link['attributes']['href'] = isset( $link['attributes']['href'] ) ? esc_url_raw( $link['attributes']['href'] ) : 'about:blank';
			$link_header                = '<' . $link['attributes']['href'] . '>; rel="preload"';
			unset( $link['attributes']['href'] );
			foreach ( $link['attributes'] as $name => $value ) {
				/*
				 * Escape the value being put into an HTTP quoted string. The grammar is:
				 *
				 *     quoted-string  = DQUOTE *( qdtext / quoted-pair ) DQUOTE
				 *     qdtext         = HTAB / SP / %x21 / %x23-5B / %x5D-7E / obs-text
				 *     quoted-pair    = "\" ( HTAB / SP / VCHAR / obs-text )
				 *     obs-text       = %x80-FF
				 *
				 * See <https://www.rfc-editor.org/rfc/rfc9110.html#section-5.6.4>. So to escape a value we need to add
				 * a backslash in front of anything character which is not qdtext.
				 */
				$escaped_value = preg_replace( '/(?=[^\t \x21\x23-\x5B\x5D-\x7E\x80-\xFF])/', '\\\\', $value );
				$link_header  .= sprintf( '; %s="%s"', $name, $escaped_value );
			}

			$link_headers[] = $link_header;
		}
		if ( count( $link_headers ) === 0 ) {
			return null;
		}

		return 'Link: ' . implode( ', ', $link_headers );
	}

	/**
	 * Counts the links.
	 *
	 * @return int Link count.
	 */
	public function count(): int {
		return count( $this->links );
	}
}
