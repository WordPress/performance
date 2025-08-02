<?php
/**
 * Helper functions used for Enqueued Assets Health Check.
 *
 * @package performance-lab
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Callback for enqueued_blocking_assets test.
 *
 * @since n.e.x.t
 *
 * @return array{
 *             label: string,
 *             status: 'good'|'recommended',
 *             badge: array{label: string, color: non-empty-string},
 *             description: string,
 *             actions: string,
 *             test: string
 *         }|array{omitted: true} Result.
 */
function perflab_aea_enqueued_blocking_assets_test(): array {
	$result = array(
		'label'       => __( 'Blocking assets', 'performance-lab' ),
		'status'      => 'good',
		'badge'       => array(
			'label' => __( 'Performance', 'performance-lab' ),
			'color' => 'blue',
		),
		'description' => '',
		'actions'     => '',
		'test'        => 'enqueued_blocking_assets',
	);

	$response = get_transient( 'aea_blocking_assets_response' );
	if ( is_wp_error( $response ) || is_array( $response ) ) {
		$retrieval_failure_result = perflab_aea_blocking_assets_retrieval_failure( $response );
		if ( null !== $retrieval_failure_result ) {
			return array_merge( $result, $retrieval_failure_result );
		}
	}

	$scripts_result = perflab_aea_enqueued_blocking_scripts();
	$styles_result  = perflab_aea_enqueued_blocking_styles();

	if ( null === $scripts_result && null === $styles_result ) {
		// The return value is validated in JavaScript at:
		// <https://github.com/WordPress/wordpress-develop/blob/d1e0a6241dcc34f4a5ed464a741116461a88d43b/src/js/_enqueues/admin/site-health.js#L65-L114>
		// If the value lacks the required keys of test, label, and description then it is omitted.
		return array( 'omitted' => true );
	}

	$result['description'] .= perflab_aea_generate_blocking_assets_table();

	if ( isset( $scripts_result ) ) {
		$result['description'] .= $scripts_result['description'];
	}
	if ( isset( $styles_result ) ) {
		$result['description'] .= $styles_result['description'];
	}

	if (
		( isset( $scripts_result ) && 'good' !== $scripts_result['status'] ) ||
		( isset( $styles_result ) && 'good' !== $styles_result['status'] )
	) {
		$result['status']  = 'recommended';
		$result['actions'] = sprintf(
			/* translators: 1: HelpHub URL. 2: Link description. 3.URL to clean cache. 4. Clean Cache text. */
			'<p><a target="_blank" href="%1$s">%2$s</a></p><p><a href="%3$s">%4$s</a></p>',
			esc_url( __( 'https://wordpress.org/support/article/optimization/', 'performance-lab' ) ),
			__( 'More info about performance optimization', 'performance-lab' ),
			esc_url( add_query_arg( 'action', 'clean_aea_audit', wp_nonce_url( admin_url( 'site-health.php' ), 'clean_aea_audit' ) ) ),
			__( 'Clean Test Cache', 'performance-lab' )
		);
	}

	return $result;
}

/**
 * Prepares the blocking scripts audit result.
 *
 * @since n.e.x.t
 *
 * @return array{status: 'good'|'recommended', description: string}|null Result.
 */
function perflab_aea_enqueued_blocking_scripts(): ?array {
	$enqueued_scripts = perflab_aea_get_total_enqueued_assets( 'scripts' );
	$bytes_enqueued   = perflab_aea_get_total_size_bytes_enqueued_assets( 'scripts' );
	if ( null === $enqueued_scripts || null === $bytes_enqueued ) {
		return null;
	}

	$result = array(
		'status'      => 'good',
		'description' => sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of blocking styles. 2.Styles size. */
					_n(
						'The amount of %1$s blocking script (size: %2$s) is acceptable.',
						'The amount of %1$s blocking scripts (size: %2$s) is acceptable.',
						$enqueued_scripts,
						'performance-lab'
					),
					$enqueued_scripts,
					size_format( $bytes_enqueued )
				)
			)
		),
	);

	/**
	 * Filters number of enqueued scripts to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $scripts_threshold Scripts threshold number. Default 30.
	 */
	$scripts_threshold = apply_filters( 'perflab_aea_enqueued_scripts_threshold', 30 );

	/**
	 * Filters size of enqueued scripts to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $scripts_size_threshold Enqueued Scripts size (in bytes) threshold. Default 300000.
	 */
	$scripts_size_threshold = apply_filters( 'perflab_aea_enqueued_scripts_byte_size_threshold', 300000 );

	if ( $enqueued_scripts > $scripts_threshold || $bytes_enqueued > $scripts_size_threshold ) {
		$result['status'] = 'recommended';

		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of blocking styles. 2.Styles size. */
					_n(
						'Your website has %1$s blocking script (size: %2$s). Try to reduce the number or to concatenate them.',
						'Your website has %1$s blocking scripts (size: %2$s). Try to reduce the number or to concatenate them.',
						$enqueued_scripts,
						'performance-lab'
					),
					$enqueued_scripts,
					size_format( $bytes_enqueued )
				)
			)
		);
	}

	// If one of the assets had an error, then fail the test even if under the threshold.
	$scripts = perflab_aea_get_blocking_assets( 'scripts' );
	if ( null !== $scripts ) {
		foreach ( $scripts as $script ) {
			if ( is_wp_error( $script['error'] ) ) {
				$result['status'] = 'recommended';
			}
		}
	}

	return $result;
}

/**
 * Prepares the blocking styles audit result.
 *
 * @since n.e.x.t
 *
 * @return array{status: string, description: string}|null Result.
 */
function perflab_aea_enqueued_blocking_styles(): ?array {
	// Omit if the test didn't run yet, omit.
	$enqueued_styles = perflab_aea_get_total_enqueued_assets( 'styles' );
	$bytes_enqueued  = perflab_aea_get_total_size_bytes_enqueued_assets( 'styles' );
	if ( null === $enqueued_styles || null === $bytes_enqueued ) {
		return null;
	}

	$result = array(
		'status'      => 'good',
		'description' => sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of blocking styles. 2.Styles size. */
					_n(
						'The amount of %1$s blocking style (size: %2$s) is acceptable.',
						'The amount of %1$s blocking styles (size: %2$s) is acceptable.',
						$enqueued_styles,
						'performance-lab'
					),
					$enqueued_styles,
					size_format( $bytes_enqueued )
				)
			)
		),
	);

	/**
	 * Filters number of enqueued styles to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $styles_threshold Styles threshold number. Default 10.
	 */
	$styles_threshold = apply_filters( 'perflab_aea_enqueued_styles_threshold', 10 );

	/**
	 * Filters size of enqueued styles to trigger warning.
	 *
	 * @since 1.0.0
	 *
	 * @param int $styles_size_threshold Enqueued styles size (in bytes) threshold. Default 100000.
	 */
	$styles_size_threshold = apply_filters( 'perflab_aea_enqueued_styles_byte_size_threshold', 100000 );

	if ( $enqueued_styles > $styles_threshold || perflab_aea_get_total_size_bytes_enqueued_assets( 'styles' ) > $styles_size_threshold ) {
		$result['status'] = 'recommended';

		$result['description'] = sprintf(
			'<p>%s</p>',
			esc_html(
				sprintf(
					/* translators: 1: Number of blocking styles. 2.Styles size. */
					_n(
						'Your website has %1$s blocking style (size: %2$s). Try to reduce the number or to concatenate them.',
						'Your website has %1$s blocking styles (size: %2$s). Try to reduce the number or to concatenate them.',
						$enqueued_styles,
						'performance-lab'
					),
					$enqueued_styles,
					size_format( $bytes_enqueued )
				)
			)
		);
	}

	// If one of the assets had an error, then fail the test even if under the threshold.
	$styles = perflab_aea_get_blocking_assets( 'styles' );
	if ( null !== $styles ) {
		foreach ( $styles as $style ) {
			if ( is_wp_error( $style['error'] ) ) {
				$result['status'] = 'recommended';
			}
		}
	}

	return $result;
}

/**
 * Handles the failure of retrieving the home page to analyze blocking assets.
 *
 * @since n.e.x.t
 *
 * @param WP_Error|array<string, mixed> $response The response from the home page retrieval.
 * @return array{status: 'recommended', description: string}|null Result, or null if there was no failure.
 */
function perflab_aea_blocking_assets_retrieval_failure( $response ): ?array {
	$result = array(
		'status'      => 'recommended',
		'description' => '',
	);

	if ( is_array( $response ) ) {
		$code    = wp_remote_retrieve_response_code( $response );
		$message = wp_remote_retrieve_response_message( $response );
		$body    = wp_remote_retrieve_body( $response );
		$header  = wp_remote_retrieve_header( $response, 'content-type' );
		if ( is_array( $header ) ) {
			$header = array_pop( $header );
		}

		// No error.
		if ( 200 === $code && '' !== $body ) {
			return null;
		}

		if ( '' === $body ) {
			$result['description'] .= '<p>' . esc_html__( 'While retrieving the home page to analyze the blocking assets, the request was successfully but response body was empty.', 'performance-lab' ) . '</p>';
		}

		if ( 200 !== $code ) {
			$result['description'] .= '<p>' . wp_kses(
				sprintf(
					/* translators: %d is the HTTP status code, %s is the status header description */
					__( 'While retrieving the home page to analyze the blocking assets, the request returned with an HTTP status of <code>%1$d %2$s</code>.', 'performance-lab' ),
					(int) $code,
					esc_html( $message )
				),
				array( 'code' => array() )
			) . '</p>';
		}

		if ( '' !== $body ) {
			$result['description'] .= '<details>';
			$result['description'] .= '<summary>' . esc_html__( 'Raw response:', 'performance-lab' ) . '</summary>';

			if ( is_string( $header ) && str_contains( $header, 'html' ) ) {
				$escaped_content        = htmlspecialchars( $body, ENT_QUOTES, 'UTF-8' );
				$result['description'] .= '<iframe srcdoc="' . $escaped_content . '" sandbox width="100%" height="300"></iframe>';
			} else {
				$result['description'] .= '<pre style="white-space: pre-wrap">' . esc_html( $body ) . '</pre>';
			}
			$result['description'] .= '</details>';
		}
	} else {
		$result['description'] = '<p>' . wp_kses(
			sprintf(
				/* translators: %1$s is the error code */
				__( 'There was an error while retrieving the home page to analyze the blocking assets, with the error code <code>%1$s</code> and the following message:', 'performance-lab' ),
				esc_html( (string) $response->get_error_code() )
			),
			array( 'code' => array() )
		) . '</p><blockquote>' . esc_html( $response->get_error_message() ) . '</blockquote>';
	}
	return $result;
}

/**
 * Gets blocking assets.
 *
 * @since n.e.x.t
 *
 * @param 'scripts'|'styles' $type Type of assets.
 * @return array<array{ src: string, size: int|null, error: WP_Error|null }>|null Blocking assets, or null if transient not set.
 */
function perflab_aea_get_blocking_assets( string $type ): ?array {
	$transient = get_transient( 'aea_blocking_assets' );
	if (
		is_array( $transient ) // If it is a WP_Error, then "Error: Cannot use object of type WP_Error as array".
		&&
		isset( $transient[ $type ] )
		&&
		is_array( $transient[ $type ] )
	) {
		$blocking_assets = array();
		foreach ( $transient[ $type ] as $blocking_asset ) {
			if (
				(
					array_key_exists( 'src', $blocking_asset )
					&&
					is_string( $blocking_asset['src'] )
				)
				&&
				(
					array_key_exists( 'size', $blocking_asset )
					&&
					( is_int( $blocking_asset['size'] ) || is_null( $blocking_asset['size'] ) )
				)
				&&
				(
					array_key_exists( 'error', $blocking_asset )
					&&
					( is_wp_error( $blocking_asset['error'] ) || is_null( $blocking_asset['error'] ) )
				)
			) {
				$blocking_assets[] = $blocking_asset;
			}
		}
		return $blocking_assets;
	} else {
		return null;
	}
}

/**
 * Gets total of enqueued assets.
 *
 * @since n.e.x.t
 *
 * @param 'scripts'|'styles' $type Type.
 * @return int|null Number of total scripts or null if transient hasn't been set.
 */
function perflab_aea_get_total_enqueued_assets( string $type ): ?int {
	$scripts = perflab_aea_get_blocking_assets( $type );
	if ( is_array( $scripts ) ) {
		return count( $scripts );
	}
	return null;
}

/**
 * Gets total size in bytes of Enqueued Assets.
 *
 * @since n.e.x.t
 *
 * @param 'scripts'|'styles' $type Type.
 * @return int|null Byte Total size or null if transient hasn't been set.
 */
function perflab_aea_get_total_size_bytes_enqueued_assets( string $type ): ?int {
	$assets = perflab_aea_get_blocking_assets( $type );
	if ( null === $assets ) {
		return null;
	}

	$total_size = 0;
	foreach ( $assets as $asset ) {
		$total_size += $asset['size'] ?? 0;
	}
	return $total_size;
}

/**
 * Gets the size of the asset in bytes.
 *
 * @since n.e.x.t
 *
 * @param string $resource_url URL of the resource.
 * @return int|WP_Error Size of the asset in bytes or WP_Error if the request fails.
 */
function perflab_aea_get_asset_size( string $resource_url ) {
	$response = wp_remote_get(
		$resource_url,
		array(
			'timeout' => 10,
			'headers' => perflab_aea_copy_basic_auth_headers( array() ),
		)
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return new WP_Error(
			'http_error',
			wp_kses(
				sprintf(
					/* translators: %d is the HTTP status code, %s is the status header description */
					__( 'Failed to retrieve the above asset with an HTTP status of <code>%1$d %2$s</code>.', 'performance-lab' ),
					(int) wp_remote_retrieve_response_code( $response ),
					esc_html( wp_remote_retrieve_response_message( $response ) )
				),
				array( 'code' => array() )
			)
		);
	}

	// TODO: A non-cacheable response should also be considered an error.
	// TODO: A size of zero could be considered an error too.
	return strlen( wp_remote_retrieve_body( $response ) );
}

/**
 * Copies HTTP Basic auth headers if present.
 *
 * @since n.e.x.t
 *
 * @param array<string, mixed> $headers Headers to copy to.
 * @return array<string, mixed> Headers with copied Basic auth headers.
 */
function perflab_aea_copy_basic_auth_headers( array $headers ): array {
	if ( isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
		$user                     = sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_USER'] ) );
		$pass                     = sanitize_text_field( wp_unslash( $_SERVER['PHP_AUTH_PW'] ) );
		$headers['Authorization'] = 'Basic ' . base64_encode( $user . ':' . $pass ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64_encode() is used here to encode the credentials for forwarding basic auth headers.
	}
	return $headers;
}

/**
 * Generates a table of blocking assets.
 *
 * @since n.e.x.t
 *
 * @return string HTML table of blocking assets.
 */
function perflab_aea_generate_blocking_assets_table(): string {
	$blocking_assets = get_transient( 'aea_blocking_assets' );
	if ( ! is_array( $blocking_assets ) || 0 === count( $blocking_assets ) ) {
		return '';
	}

	$table  = '<table class="wp-list-table widefat striped"><thead><tr>';
	$table .= '<th scope="col">' . esc_html__( 'Type', 'performance-lab' ) . '</th>';
	$table .= '<th scope="col">' . esc_html__( 'Source', 'performance-lab' ) . '</th>';
	$table .= '<th scope="col">' . esc_html__( 'Size', 'performance-lab' ) . '</th>';
	$table .= '<th scope="col">' . esc_html__( 'Status', 'performance-lab' ) . '</th>';
	$table .= '</tr></thead><tbody>';

	$asset_types = array(
		'scripts' => __( 'Script', 'performance-lab' ),
		'styles'  => __( 'Style', 'performance-lab' ),
	);
	foreach ( $asset_types as $type => $label ) {
		if ( isset( $blocking_assets[ $type ] ) && is_array( $blocking_assets[ $type ] ) ) {
			foreach ( $blocking_assets[ $type ] as $asset ) {
				$has_error = is_wp_error( $asset['error'] );

				$table .= $has_error ? '<tr style="background-color: #ffecec;">' : '<tr>';
				$table .= '<td>' . esc_html( $label ) . '</td>';
				$table .= '<td>' . esc_url( $asset['src'] );
				if ( $has_error ) {
					$table .= '<p>' . wp_kses( $asset['error']->get_error_message(), array( 'code' => array() ) ) . '</p>';
				}
				$table .= '</td>';
				$table .= '<td>';
				if ( is_int( $asset['size'] ) ) {
					$table .= str_replace( ' ', '&nbsp;', (string) size_format( $asset['size'] ) );
				} else {
					$table .= esc_html__( 'N/A', 'performance-lab' );
				}
				$table .= '</td>';
				$table .= '<td>' . esc_html( $has_error ? __( 'Error', 'performance-lab' ) : __( 'OK', 'performance-lab' ) ) . '</td>';
				$table .= '</tr>';
			}
		}
	}

	$table .= '</tbody></table>';

	return $table;
}
