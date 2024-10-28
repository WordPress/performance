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

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins = get_plugins();

		// Map TextDomain to Name. This only works when the handle matches the text domain.
		$plugin_name_map = array();
		foreach ( $all_plugins as $plugin_slug => $plugin_data ) {
			$plugin_name_map[ $plugin_data['TextDomain'] ] = $plugin_data['Name'];
		}

		// Save the data to the error log so you can see what the array format is like.
		error_log( print_r( $plugin_name_map, true ) );

		foreach ( $wp_scripts->done as $handle ) {
			$name = isset( $plugin_name_map[ $handle ] ) ? $plugin_name_map[ $handle ] : $handle;
			$src = $wp_scripts->registered[ $handle ]->src;
			perflab_performance_marks()->add_mark(
				'script_enqueue::' . $handle,
				array(
					'slug' => $handle,
					'src'  => $src,
					'name' => $name,
				)
			);
		}
		error_log( "send marks!" );
 		if ( empty( $this->marks ) ) {
			error_log( "no marks!" );
			return;
		}
		echo ( '<script>' );
		echo ( 'console.log( "sending marks" );' );
		$x = 100;
		foreach ( $this->marks as $mark_slug => $mark_args ) {
			echo sprintf(
				'performance.mark( "%s", { detail: %s, startTime: %s } );',
				$mark_slug,
				wp_json_encode( $mark_args ),
				$x
			);
			$x = $x + 10;
		}
		echo( '</script>' );
	}
}
