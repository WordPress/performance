<?php
/**
 * @package performance-lab
 * @since 4.0.0
 */

/**
 * Class Audit_Assets_Mock_Assets mocks assets for testing.
 *
 * @since 4.0.0
 */
class Audit_Assets_Mock_Assets {

	/**
	 * Mocked assets handles.
	 *
	 * @var array<string>
	 */
	private static $mocked_assets_handles = array();

	/**
	 * Mocked responses for HTTP requests.
	 *
	 * @var array<string, mixed>
	 */
	public static $mocked_responses = array();

	/**
	 * Mocks the assets for testing.
	 *
	 * @param 'scripts'|'styles'   $type             Type.
	 * @param int                  $number_of_assets Number of assets to mock.
	 * @param int                  $error_count      Error count to mock.
	 * @param array<string, mixed> $assets           Array of assets to mock.
	 * @return array<string, mixed> Array of assets with mocked data.
	 */
	public static function mock_assets( string $type, int $number_of_assets = 5, int $error_count = 0, array $assets = array() ): array {
		$assets[ $type ] = array();
		for ( $i = 1; $i <= $number_of_assets; $i++ ) {
			$id = wp_generate_uuid4();
			if ( 'scripts' === $type ) {
				$src    = home_url( '/script-' . $id . '.js' );
				$handle = 'mock-script-' . $id;
				wp_enqueue_script( $handle, $src, array(), null );
			} else {
				$src    = home_url( '/style-' . $id . '.css' );
				$handle = 'mock-style-' . $id;
				wp_enqueue_style( $handle, $src, array(), null );
			}

			self::$mocked_assets_handles[] = $handle;

			if ( $error_count > 0 ) {
				--$error_count;
				self::$mocked_responses[ $src ] = new WP_Error( '404', 'Not found' );
				$assets[ $type ][]              = array(
					'src'   => $src,
					'size'  => null,
					'error' => self::$mocked_responses[ $src ],
				);
			} else {
				self::$mocked_responses[ $src ] = array(
					'code'    => 200,
					'body'    => str_repeat( 'A', 1000 ),
					'headers' => array( 'cache-control' => 'max-age=3600' ),
				);
				$assets[ $type ][]              = array(
					'src'   => $src,
					'size'  => 1000,
					'error' => null,
				);
			}
		}

		return $assets;
	}

	/**
	 * Returns a mocked HTML structure for testing.
	 *
	 * @return string Mocked HTML structure.
	 */
	public static function get_mocked_html(): string {
		// Dequeue core blocks assets to avoid conflicts.
		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				wp_dequeue_style( 'wp-block-library' );
			}
		);

		$mock_html  = '<!DOCTYPE html><head>';
		$mock_html .= get_echo( 'wp_head' );
		$mock_html .= '</head><body>';
		$mock_html .= get_echo( 'wp_footer' );
		$mock_html .= '</body></html>';
		return $mock_html;
	}

	/**
	 * Adds a mocked response for a specific URL.
	 *
	 * @param array<string|mixed> $responses An array containing the URLs and the mocked responses.
	 */
	public static function add_mock_responses( array $responses ): void {
		foreach ( $responses as $response ) {
			self::$mocked_responses[ $response['url'] ] = $response['response'];
		}
	}

	/**
	 * Clears the mocked responses and assets.
	 */
	public static function clear_mocked(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'dequeue_mocked_assets' ) );
		do_action( 'wp_enqueue_scripts' );
		remove_action( 'wp_enqueue_scripts', array( self::class, 'dequeue_mocked_assets' ) );

		self::$mocked_responses      = array();
		self::$mocked_assets_handles = array();
	}

	/**
	 * Dequeues mocked assets.
	 */
	public static function dequeue_mocked_assets(): void {
		foreach ( self::$mocked_assets_handles as $handle ) {
			wp_dequeue_script( $handle );
			wp_dequeue_style( $handle );
		}
	}

	/**
	 * Mocks HTTP requests for tests.
	 *
	 * @param array<string, mixed> $additional_responses Additional mocked responses.
	 */
	public static function mock_requests( array $additional_responses = array() ): void {
		remove_all_filters( 'pre_http_request' );

		self::$mocked_responses[ home_url( '/' ) ] = array(
			'code'    => 200,
			'body'    => self::get_mocked_html(),
			'message' => 'OK',
		);
		self::add_mock_responses( $additional_responses );

		add_filter(
			'pre_http_request',
			static function ( $preempt, $parsed_args, $url ) {
				$url = remove_query_arg( 'cache_bust', $url );
				if ( isset( self::$mocked_responses[ $url ] ) ) {
					if ( is_wp_error( self::$mocked_responses[ $url ] ) ) {
						return self::$mocked_responses[ $url ];
					}

					return array(
						'response' => self::$mocked_responses[ $url ],
						'body'     => self::$mocked_responses[ $url ]['body'] ?? '',
						'headers'  => self::$mocked_responses[ $url ]['headers'] ?? array(),
					);
				}
				return $preempt;
			},
			10,
			3
		);
	}
}
