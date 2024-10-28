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
		echo ( '<script>' );
		foreach ( $this->marks as $mark_slug => $mark_args ) {
			echo esc_html(
				sprintf(
					'performance.mark( "%1$s", { detail: %2$s } );',
					$mark_slug,
					wp_json_encode( $mark_args )
				)
			);
		}
		echo( '</script>' );
	}
}
