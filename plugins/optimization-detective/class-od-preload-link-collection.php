<?php
/**
 * Optimization Detective: OD_Preload_Link_Collection class
 *
 * @package optimization-detective
 * @since n.e.x.t
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
 *                   href?: non-falsy-string,
 *                   imagesrcset?: non-falsy-string,
 *                   imagesizes?: non-falsy-string,
 *                   crossorigin?: ''|'anonymous'|'use-credentials',
 *                   fetchpriority?: 'high'|'low'|'auto',
 *                   as: 'audio'|'document'|'embed'|'fetch'|'font'|'image'|'object'|'script'|'style'|'track'|'video'|'worker'
 *               }
 *
 * @since n.e.x.t
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
	 * Get adjacent-deduplicated links.
	 *
	 * When two links are identical except for their minimum/maximum widths which are also consecutive, then merge them
	 * together.
	 *
	 * @return array<int, Link> Links with adjacent-duplicates merged together.
	 */
	private function get_adjacent_deduplicated_links(): array {
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

		return array_reduce(
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

					// Update the last link with the new maximum viewport with.
					$carry[ count( $carry ) - 1 ] = $last_link;
				} else {
					$carry[] = $link;
				}
				return $carry;
			},
			array()
		);
	}

	/**
	 * Gets the HTML for the link tags.
	 *
	 * @return string Link tags HTML.
	 */
	public function get_html(): string {
		$link_tags = array();

		foreach ( $this->get_adjacent_deduplicated_links() as $link ) {
			$media_features = array( 'screen' );
			if ( null !== $link['minimum_viewport_width'] && $link['minimum_viewport_width'] > 0 ) {
				$media_features[] = sprintf( '(min-width: %dpx)', $link['minimum_viewport_width'] );
			}
			if ( null !== $link['maximum_viewport_width'] && PHP_INT_MAX !== $link['maximum_viewport_width'] ) {
				$media_features[] = sprintf( '(max-width: %dpx)', $link['maximum_viewport_width'] );
			}
			$link['attributes']['media'] = implode( ' and ', $media_features );

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
	 * Counts the links.
	 *
	 * @return int Link count.
	 */
	public function count(): int {
		return count( $this->links );
	}
}
