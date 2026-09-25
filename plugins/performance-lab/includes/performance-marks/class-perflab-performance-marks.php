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

		// Collect any scripts output directly.
		remove_action( 'wp_footer', array( perflab_performance_marks(), 'send_marks' ), 999 );
		$manually_output_scripts = $this->get_manually_output_scripts();

		// Add the manually output scripts to the marks.
		foreach ( $manually_output_scripts as $script ) {

			$this->add_mark(
				'attribution::plugin_output::' . $script['slug'],
				array(
					'path' => $script['path'],
					'slug' => $script['slug'],
					'name' => $script['name'],
				)
			);
		}

		foreach ( $wp_scripts->done as $handle ) {
			$src = $wp_scripts->registered[ $handle ]->src;
			if ( false === $src ) {
				continue;
			}
			// Gather the slug, name at relative path.
			$attribution_data = $this->get_script_data_from_src( $src );

			// If the slug is core, the is a core:enqueue, otherwise if the src contains 'themes' this is a theme:enqueue, otherwise it is a plugin:enqueue.
			$mark_slug = 'attribution::' . ( 'core' === $attribution_data['slug'] ? 'core' : ( str_contains( $src, 'themes' ) ? 'theme' : 'plugin' ) ) . '_enqueue::' . $handle;
			perflab_performance_marks()->add_mark(
				$mark_slug,
				array(
					'path' => $attribution_data['path'],
					'slug' => $attribution_data['slug'],
					'name' => $attribution_data['name'],
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
			++$x;
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
	private function get_script_data_from_src( string $src ): array {

		// Get just the local path for the src (removing the local domain).
		$src = str_replace( get_site_url(), '', $src );

		if ( str_starts_with( $src, '/wp-includes/' ) ) {
			return array(
				'slug' => 'core',
				'name' => 'WordPress Core',
				'path' => $src,
			);
		}

		// Extract the slug from $src, eg. "/wp-content/plugins/{slug}/path/to/script.js".
		$slugs = explode( '/', $src );
		$slug  = $slugs[3];

		// If the src contains 'plugins', extract the plugin data.
		if ( str_contains( $src, 'themes' ) ) {
			return array(
				'slug' => $slug,
				'name' => wp_get_theme()->get( 'Name' ),
				'path' => $src,
			);
		}
		$script_data = $this->get_plugin_data_by_slug( $slug );

		return array(
			'slug' => $script_data['slug'],
			'name' => $script_data['name'],
			'path' => $src,
		);
	}

	/**
	 * Get data for plugin by slug.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $slug The plugin slug.
	 * @return array<string, string> The plugin slug and name.
	 */
	private function get_plugin_data_by_slug( string $slug ): array {
		if ( '' === $slug ) {
			return array(
				'slug' => '',
				'name' => '',
			);
		}
		foreach ( $this->plugins_data as $plugin_slug => $plugin_data ) {
			if ( $slug === $plugin_data['TextDomain'] ) {
				return array(
					'slug' => $plugin_data['TextDomain'],
					'name' => $plugin_data['Name'],
				);
			}
		}
		return array(
			'slug' => '',
			'name' => '',
		);
	}

	/**
	 * Get all of the manually output scripts.
	 *
	 * Check all plugins hooked to wp_head or wp_footer to see if they are enqueuing scripts.
	 * Run all hooks using output buffering, then review content for any script handles that are enqueued.
	 * Use the HTML API to parse for script handle, then add that to the performance marks.
	 *
	 * For each script, return the plugin slug and name and the script path.
	 *
	 * @since n.e.x.t
	 *
	 * @return array<int, array<string, bool|string>> Array of script handles with plugin slug and name.
	 */
	private function get_manually_output_scripts(): array {
		$scripts = array();
		$hooks   = array(
			'wp_head',
			'wp_footer',
		);
		foreach ( $hooks as $hook ) {
			// Get all callbacks hooked on this hook and invoke them one at a time.
			$callbacks = $GLOBALS['wp_filter'][ $hook ];
			foreach ( $callbacks as $priority => $sub_callbacks ) {
				foreach ( $sub_callbacks as $callback ) {
					// Capture the output HTML.
					ob_start();
					call_user_func( $callback['function'], array() );
					$html = ob_get_clean();
					// Parse the HTML for any script handles.
					if ( empty( $html ) ) {
						continue;
					}
					$processor = new WP_HTML_Tag_Processor( $html );
					while ( $processor->next_tag() ) {
						if ( 'SCRIPT' === $processor->get_tag() ) {
							$src         = $processor->get_attribute( 'src' );
							$plugin_slug = '';
							if ( ! empty( $src ) ) {
								if ( is_array( $callback['function'] ) ) {
									$class_name  = $callback['function'][0]; // Class.
									$method_name = $callback['function'][1]; // Method.
									try {
										$reflection_method = new ReflectionMethod( $class_name, $method_name );
										$file_path         = $reflection_method->getFileName();
										$plugin_slug       = $this->get_slug_from_path( $file_path );
									} catch ( ReflectionException $e ) {
										continue;
									}
								} else {
									$function_name = $callback['function'];
									try {
										$reflection_function = new ReflectionFunction( $function_name );
										$file_path           = $reflection_function->getFileName();
										$plugin_slug         = $this->get_slug_from_path( $file_path );
									} catch ( ReflectionException $e ) {
										continue;
									}
									$plugin_data = $this->get_plugin_data_by_slug( $plugin_slug );
									$scripts[]   = array(
										'path' => $src,
										'slug' => empty( $plugin_data['slug'] ) ? 'core' : $plugin_data['slug'],
										'name' => empty( $plugin_data['name'] ) ? 'WordPressCore' : $plugin_data['name'],
									);
								}
							}
						}
					}
				}
			}
		}
		return $scripts;
	}

	/**
	 * Helper to get the plugin slug from a file path.
	 *
	 * @since n.e.x.t
	 *
	 * @param string|false $file_path The file path.
	 * @return string The plugin slug.
	 */
	private function get_slug_from_path( $file_path ): string {
		if ( false === $file_path ) {
			return '';
		}
		$pattern = '#/(?:plugins|themes)/([^/]+)/#'; // Match anything after '/plugins/' or '/themes/' up to the next '/'.
		if ( false !== preg_match( $pattern, $file_path, $matches ) ) {
			return $matches[1];
		}
		return '';
	}
}
