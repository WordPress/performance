<?php
/**
 * Server Timing API: Perflab_Performance_Marks class
 *
 * @package performance-lab
 * @since n.e.x.t
 */

/**
 * Class controlling Dev Tools Performance Marks.
 *
 * Leverages the Dev Tools extensibility API to add custom performance data. See https://developer.chrome.com/docs/devtools/performance/extension.
 *
 * @since n.e.x.t
 */
class Perflab_Performance_Marks {

	/**
	 * Map of stored metrics that will be added as Dev Tools Performance marks.
	 *
	 * @since n.e.x.t
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $marks = array();

	/**
	 * Array of all plugins and their data.
	 *
	 * @since n.e.x.t
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $plugins_data = array();

	/**
	 * Initialize the class including plugin data.
	 *
	 * @since n.e.x.t
	 */
	public function __construct() {
		global $wp_scripts;

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$this->plugins_data = get_plugins();
	}

	/**
	 * Add a mark to the list of marks.
	 *
	 * @since n.e.x.t
	 *
	 * @param string               $mark_slug The slug of the mark.
	 * @param array<string, mixed> $args      Arguments for the mark.
	 */
	public function add_mark( string $mark_slug, array $args ): void {
		$this->marks[ $mark_slug ] = $args;
	}

	/**
	 * Get a mark by its slug.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $mark_slug The slug of the mark.
	 * @return array<string, mixed>|null The mark, or null if not found.
	 */
	public function get_mark( string $mark_slug ): ?array {
		return $this->marks[ $mark_slug ] ?? null;
	}

	/**
	 * Get all of the marks.
	 *
	 * @since n.e.x.t
	 *
	 * @return array<string, array<string, mixed>> All of the marks.
	 */
	public function get_all_marks(): array {
		return $this->marks;
	}

	/**
	 * Send the marks to the Dev Tools Performance panel.
	 *
	 * Outputs inline JavaScript that uses the Dev Tools Performance API to add marks to the timeline.
	 *
	 * This function should be called in the footer after all data has been collected.
	 *
	 * @since n.e.x.t
	 */
	public function send_marks(): void {
		global $wp_scripts;

		foreach ( $wp_scripts->done as $handle ) {
			$src = $wp_scripts->registered[ $handle ]->src;
			if ( false === $src ) {
				continue;
			}
			// Gather the plugin slug, name at relative path.
			$plugin_data = $this->get_plugin_data_from_src( $src );
			perflab_performance_marks()->add_mark(
				'script_enqueue::' . $handle,
				array(
					'path' => $plugin_data['path'],
					'slug' => $plugin_data['slug'],
					'name' => $plugin_data['name'],
				)
			);
		}
		if ( empty( $this->marks ) ) {
			return;
		}
		echo ( '<script>' );
		echo 'console.log( "sending marks" );';
		$x = 100;
		foreach ( $this->marks as $mark_slug => $mark_args ) {
			printf(
				'performance.mark( "%s", { detail: %s, startTime: %s } );',
				esc_attr( $mark_slug ),
				wp_json_encode( $mark_args ),
				esc_attr( (string) $x )
			);
			$x = $x + 10;
		}
		echo( '</script>' );
	}

	/**
	 * Helper function to get the plugin slug and name when passed a script path.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $src The script path.
	 * @return array<string, string> The plugin slug, name and path.
	 */
	private function get_plugin_data_from_src( string $src ): array {

		// Get just the local path for the src (removing the local domain).
		$src = str_replace( get_site_url(), '', $src );

		if ( str_starts_with( $src, '/wp-includes/' ) ) {
			return array(
				'slug' => 'core',
				'name' => 'Core',
				'path' => $src,
			);
		}

		// Extract the slug from $src, eg. "/wp-content/plugins/{slug}/path/to/script.js".
		$slugs = explode( '/', $src );
		$slug  = $slugs[3];

		foreach ( $this->plugins_data as $plugin_slug => $plugin_data ) {
			if ( $slug === $plugin_data['TextDomain'] ) {
				return array(
					'slug' => $plugin_data['TextDomain'],
					'name' => $plugin_data['Name'],
					'path' => $src,
				);
			}
		}
		return array(
			'slug' => '',
			'name' => '',
			'path' => $src,
		);
	}
}
