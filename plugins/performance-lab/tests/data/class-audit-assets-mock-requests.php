<?php
/**
 * @package performance-lab
 * @since n.e.x.t
 */

use PHP_CodeSniffer\Tokenizers\PHP;

/**
 * Class Audit_Assets_Mock_Requests mocks HTTP requests for testing.
 *
 * @since n.e.x.t
 */
class Audit_Assets_Mock_Requests {

	/**
	 * Mocked responses for HTTP requests.
	 *
	 * @var array<string, mixed>
	 */
	private static $mocked_responses = array();

	/**
	 * Mocks the assets for testing.
	 *
	 * @param 'scripts'|'styles' $type             Type.
	 * @param int                $number_of_assets Number of assets to mock.
	 */
	public static function mock_assets( string $type, int $number_of_assets = 5 ): void {
		for ( $i = 1; $i <= $number_of_assets; $i++ ) {
			if ( 'scripts' === $type ) {
				$src = home_url( '/script.js' );
				wp_enqueue_script( "mock-script-{$i}", $src, array(), null );
				self::$mocked_responses[ $src ] = array(
					'code' => 200,
					'body' => str_repeat( 'A', 1000 ),
				);
			} elseif ( 'styles' === $type ) {
				$src = home_url( '/style.css' );
				wp_enqueue_style( "mock-style-{$i}", $src, array(), null );
				self::$mocked_responses[ $src ] = array(
					'code' => 200,
					'body' => str_repeat( 'A', 1000 ),
				);
			}
		}

		self::$mocked_responses[ home_url( '/' ) ] = array(
			'code' => 200,
			'body' => self::get_mocked_html(),
		);
	}

	public static function get_mocked_html(): string {
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
	 * Mocks HTTP requests for tests.
	 */
	public static function mock_requests(): void {
		remove_all_filters( 'pre_http_request' );
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
					);
				}
				return $preempt;
			},
			10,
			3
		);
	}
}
