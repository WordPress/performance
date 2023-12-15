<?php
/**
 * Class 'PLSR_URL_Pattern_Prefixer'.
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Class for prefixing URL patterns.
 *
 * @since n.e.x.t
 */
class PLSR_URL_Pattern_Prefixer {

	/**
	 * Map of `$context_string => $base_path` pairs.
	 *
	 * @since n.e.x.t
	 * @var array
	 */
	private $contexts;

	/**
	 * Constructor.
	 *
	 * @since n.e.x.t
	 *
	 * @param array $contexts Optional. Map of `$context_string => $base_path` pairs. Default is the contexts returned
	 *                        by the {@see PLSR_URL_Pattern_Prefixer::get_default_contexts()} method.
	 */
	public function __construct( array $contexts = array() ) {
		if ( $contexts ) {
			$this->contexts = array_map( 'trailingslashit', $contexts );
		} else {
			$this->contexts = self::get_default_contexts();
		}
	}

	/**
	 * Prefixes the given URL path pattern with the base path for the given context.
	 *
	 * This ensures that these path patterns work correctly on WordPress subdirectory sites, for example in a multisite
	 * network, or when WordPress itself is installed in a subdirectory of the hostname.
	 *
	 * The given URL path pattern is only prefixed if it does not already include the expected prefix.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $path_pattern URL pattern starting with the path segment.
	 * @param string $context      Optional. Either 'home' (any frontend content) or 'site' (content relative to the
	 *                             directory that WordPress is installed in). Default 'home'.
	 * @return string URL pattern, prefixed as necessary.
	 */
	public function prefix_path_pattern( string $path_pattern, string $context = 'home' ): string {
		// If context path does not exist, the context is invalid.
		if ( ! isset( $this->contexts[ $context ] ) ) {
			_doing_it_wrong(
				__FUNCTION__,
				esc_html(
					sprintf(
						/* translators: %s: context string */
						__( 'Invalid context %s.', 'performance-lab' ),
						$context
					)
				),
				'Performance Lab n.e.x.t'
			);
			return $path_pattern;
		}

		// If the path already starts with the context path (including '/'), there is nothing to prefix.
		if ( str_starts_with( $path_pattern, $this->contexts[ $context ] ) ) {
			return $path_pattern;
		}

		return $this->contexts[ $context ] . ltrim( $path_pattern, '/' );
	}

	/**
	 * Returns the default contexts used by the class.
	 *
	 * @since n.e.x.t
	 *
	 * @return array Map of `$context_string => $base_path` pairs.
	 */
	public static function get_default_contexts(): array {
		return array(
			'home' => trailingslashit( wp_parse_url( home_url( '/' ), PHP_URL_PATH ) ),
			'site' => trailingslashit( wp_parse_url( site_url( '/' ), PHP_URL_PATH ) ),
		);
	}
}
