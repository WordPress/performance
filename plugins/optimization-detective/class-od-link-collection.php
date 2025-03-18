<?php
/**
 * Optimization Detective: OD_Link_Collection class
 *
 * @package optimization-detective
 * @since 0.3.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Collection for links added to the document.
 *
 * @phpstan-type Link array{
 *                   attributes: LinkAttributes,
 *                   minimum_viewport_width: int<0, max>|null,
 *                   maximum_viewport_width: int<1, max>|null
 *               }
 *
 * @phpstan-type LinkAttributes array{
 *                   rel: 'preload'|'modulepreload'|'preconnect',
 *                   href?: non-empty-string,
 *                   imagesrcset?: non-empty-string,
 *                   imagesizes?: non-empty-string,
 *                   crossorigin?: 'anonymous'|'use-credentials',
 *                   fetchpriority?: 'high'|'low'|'auto',
 *                   as?: 'audio'|'document'|'embed'|'fetch'|'font'|'image'|'object'|'script'|'style'|'track'|'video'|'worker',
 *                   media?: non-empty-string,
 *                   type?: non-empty-string,
 *                   integrity?: non-empty-string,
 *                   referrerpolicy?: 'no-referrer'|'no-referrer-when-downgrade'|'origin'|'origin-when-cross-origin'|'unsafe-url'
 *               }
 *
 * @since 0.3.0
 * @since 0.4.0 Renamed from OD_Preload_Link_Collection.
 */
final class OD_Link_Collection implements Countable {

	/**
	 * Links grouped by rel type.
	 *
	 * @since 0.4.0
	 *
	 * @var array<string, Link[]>
	 */
	private $links_by_rel = array();

	/**
	 * Adds link.
	 *
	 * @since 0.3.0
	 *
	 * @phpstan-param LinkAttributes $attributes
	 *
	 * @param array            $attributes             Attributes.
	 * @param int<0, max>|null $minimum_viewport_width Minimum width (exclusive) or null if not bounded or relevant.
	 * @param int<1, max>|null $maximum_viewport_width Maximum width (inclusive) or null if not bounded (i.e. infinity) or relevant.
	 *
	 * @throws InvalidArgumentException When invalid arguments are provided.
	 */
	public function add_link( array $attributes, ?int $minimum_viewport_width = null, ?int $maximum_viewport_width = null ): void {
		$throw_invalid_argument_exception = static function ( string $message ): void {
			throw new InvalidArgumentException( esc_html( $message ) );
		};
		if ( ! array_key_exists( 'rel', $attributes ) ) {
			$throw_invalid_argument_exception(
				/* translators: %s: rel */
				sprintf( __( 'The "%s" attribute must be provided.', 'optimization-detective' ), 'rel' )
			);
		}
		if ( 'preload' === $attributes['rel'] && ! array_key_exists( 'as', $attributes ) ) {
			$throw_invalid_argument_exception(
				/* translators: 1: link, 2: rel=preload, 3: 'as' attribute name */
				sprintf( __( 'A %1$s with %2$s must include an "%3$s" attribute.', 'optimization-detective' ), 'link', 'rel=preload', 'as' )
			);
		} elseif ( 'preconnect' === $attributes['rel'] && ! array_key_exists( 'href', $attributes ) ) {
			$throw_invalid_argument_exception(
				/* translators: 1: link, 2: rel=preconnect, 3: 'href' attribute name */
				sprintf( __( 'A %1$s with %2$s must include an "%3$s" attribute.', 'optimization-detective' ), 'link', 'rel=preconnect', 'href' )
			);
		}
		if ( ! array_key_exists( 'href', $attributes ) && ! array_key_exists( 'imagesrcset', $attributes ) ) {
			$throw_invalid_argument_exception(
				/* translators: 1: 'href' attribute name, 2: 'imagesrcset' attribute name */
				sprintf( __( 'Either the "%1$s" or "%2$s" attribute must be supplied.', 'optimization-detective' ), 'href', 'imagesrcset' )
			);
		}
		if ( null !== $minimum_viewport_width && $minimum_viewport_width < 0 ) {
			$throw_invalid_argument_exception( __( 'Minimum width must be at least zero.', 'optimization-detective' ) );
		}
		if ( null !== $maximum_viewport_width && ( $maximum_viewport_width < $minimum_viewport_width || $maximum_viewport_width < 0 ) ) {
			$throw_invalid_argument_exception( __( 'Maximum width must be greater than zero and greater than the minimum width.', 'optimization-detective' ) );
		}
		foreach ( array( 'rel', 'href', 'imagesrcset', 'imagesizes', 'crossorigin', 'fetchpriority', 'as', 'integrity', 'referrerpolicy' ) as $attribute_name ) {
			if ( array_key_exists( $attribute_name, $attributes ) && ! is_string( $attributes[ $attribute_name ] ) ) {
				$throw_invalid_argument_exception( __( 'Link attributes must be strings.', 'optimization-detective' ) );
			}
		}

		$this->links_by_rel[ $attributes['rel'] ][] = array(
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
	 * @since 0.4.0
	 *
	 * @return LinkAttributes[] Prepared links with adjacent-duplicates merged together and media attributes added.
	 */
	private function get_prepared_links(): array {
		$links_by_rel = array_values( $this->links_by_rel );
		if ( count( $links_by_rel ) === 0 ) {
			// This condition is needed for PHP 7.2 and PHP 7.3 in which array_merge() fails if passed a spread empty array: 'array_merge() expects at least 1 parameter, 0 given'.
			return array();
		}

		return array_merge(
			...array_map(
				function ( array $links ): array {
					return $this->merge_consecutive_links( $links );
				},
				$links_by_rel
			)
		);
	}

	/**
	 * Merges consecutive links.
	 *
	 * @since 0.4.0
	 *
	 * @param Link[] $links Links.
	 * @return LinkAttributes[] Merged consecutive links.
	 */
	private function merge_consecutive_links( array $links ): array {

		usort(
			$links,
			/**
			 * Comparator.
			 *
			 * The links are sorted first by the 'href' attribute to group identical URLs together.
			 * If the 'href' attributes are the same, the links are then sorted by 'minimum_viewport_width'.
			 *
			 * @param Link $a First link.
			 * @param Link $b Second link.
			 * @return int Comparison result.
			 */
			static function ( array $a, array $b ): int {
				// Get href values, defaulting to empty string if not present.
				$href_a = $a['attributes']['href'] ?? '';
				$href_b = $b['attributes']['href'] ?? '';

				$href_comparison = strcmp( $href_a, $href_b );
				if ( 0 === $href_comparison ) {
					return $a['minimum_viewport_width'] <=> $b['minimum_viewport_width'];
				}

				return $href_comparison;
			}
		);

		/**
		 * Deduplicated adjacent links.
		 *
		 * @var Link[] $prepared_links
		 */
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
					is_array( $last_link )
					&&
					$last_link['attributes'] === $link['attributes']
					&&
					is_int( $last_link['minimum_viewport_width'] )
					&&
					is_int( $last_link['maximum_viewport_width'] )
					&&
					$last_link['maximum_viewport_width'] === $link['minimum_viewport_width']
				) {
					$last_link['maximum_viewport_width'] = null === $link['maximum_viewport_width'] ? null : max( $last_link['maximum_viewport_width'], $link['maximum_viewport_width'] );

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
				$media_query = od_generate_media_query( $link['minimum_viewport_width'], $link['maximum_viewport_width'] );
				if ( null !== $media_query ) {
					if ( ! isset( $link['attributes']['media'] ) ) {
						$link['attributes']['media'] = $media_query;
					} else {
						$link['attributes']['media'] .= " and $media_query";
					}
				}
				return $link['attributes'];
			},
			$prepared_links
		);
	}

	/**
	 * Gets the HTML for the link tags.
	 *
	 * @since 0.3.0
	 *
	 * @return string Link tags HTML.
	 */
	public function get_html(): string {
		$link_tags = array();

		foreach ( $this->get_prepared_links() as $link ) {
			$link_tag = '<link data-od-added-tag';
			foreach ( $link as $name => $value ) {
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
	 * @since 0.4.0
	 *
	 * @return non-empty-string|null Link HTTP response header, or null if there are none.
	 */
	public function get_response_header(): ?string {
		$link_headers = array();

		foreach ( $this->get_prepared_links() as $link ) {
			if ( isset( $link['href'] ) ) {
				$link['href'] = $this->encode_url_for_response_header( $link['href'] );
			} else {
				// The about:blank is present since a Link without a reference-uri is invalid so any imagesrcset would otherwise not get downloaded.
				$link['href'] = 'about:blank';
			}

			// Encode the URLs in the srcset.
			if ( isset( $link['imagesrcset'] ) ) {
				$link['imagesrcset'] = join(
					', ',
					array_map(
						function ( $image_candidate ) {
							// Parse out the URL to separate it from the descriptor.
							$image_candidate_parts = (array) preg_split( '/\s+/', (string) $image_candidate, 2 );

							// Encode the URL.
							$image_candidate_parts[0] = $this->encode_url_for_response_header( (string) $image_candidate_parts[0] );

							// Re-join the URL with the descriptor.
							return implode( ' ', $image_candidate_parts );
						},
						(array) preg_split( '/\s*,\s*/', $link['imagesrcset'] )
					)
				);
			}

			$link_header = '<' . $link['href'] . '>';
			unset( $link['href'] );
			foreach ( $link as $name => $value ) {
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
	 * Encodes a URL for serving in an HTTP response header.
	 *
	 * @since 1.0.0
	 *
	 * @param string $url URL to percent encode.
	 * @return string Percent-encoded URL.
	 */
	private function encode_url_for_response_header( string $url ): string {
		// Encode characters not allowed in a URL per RFC 3986 (anything that is not among the reserved and unreserved characters).
		$encoded_url = (string) preg_replace_callback(
			'/[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/',
			static function ( $matches ) {
				return rawurlencode( $matches[0] );
			},
			$url
		);
		return esc_url_raw( $encoded_url );
	}

	/**
	 * Counts the links.
	 *
	 * @since 0.3.0
	 *
	 * @return non-negative-int Link count.
	 */
	public function count(): int {
		return array_sum(
			array_map(
				static function ( array $links ): int {
					return count( $links );
				},
				array_values( $this->links_by_rel )
			)
		);
	}
}
