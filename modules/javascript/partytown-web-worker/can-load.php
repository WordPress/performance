<?php
/**
 * Can load function to determine if Partytown Web Worker module can be loaded.
 *
 * @since   n.e.x.t
 * @package partytown-web-worker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! function_exists( 'perflab_partytown_web_worker_can_load' ) ) {

	/**
	 * Check if Partytown Web Worker module can be loaded.
	 *
	 * @since n.e.x.t
	 * @return bool|WP_Error True if the module can be loaded, WP_Error otherwise.
	 */
	function perflab_partytown_web_worker_can_load() {
		$errors = new WP_Error();

		// Check for Partytown JavaScript library.
		if ( ! file_exists( __DIR__ . '/assets/js/partytown/partytown.js' ) ) {
			$errors->add(
				'partytown_web_worker_library_missing',
				sprintf(
					/* translators: %s: npm install && npm run build-plugins */
					__( 'Partytown library is missing from the plugin. Please do %s to install it.', 'performance-lab' ),
					wp_kses(
						'<code>npm install &amp;&amp; npm run build-plugins</code>',
						array(
							'code' => array(),
						)
					)
				)
			);
		}

		// Check for HTTPS.
		if (
			! is_ssl()
			&&
			( strpos( get_bloginfo( 'wpurl' ), 'https' ) !== 0 )
			&&
			( strpos( get_bloginfo( 'url' ), 'https' ) !== 0 )
		) {
			$errors->add(
				'partytown_web_worker_ssl_error',
				__( 'Partytown Web Worker Module requires a secure HTTPS connection to be enabled.', 'performance-lab' )
			);
		}

		return $errors->has_errors() ? $errors : true;
	}
}

if ( ! function_exists( 'perflab_partytown_web_worker_errors_admin_notice' ) ) {

	/**
	 * Display admin notice if Partytown Web Worker module cannot be loaded.
	 *
	 * @since n.e.x.t
	 * @return void
	 */
	function perflab_partytown_web_worker_errors_admin_notice() {
		$errors = perflab_partytown_web_worker_can_load();

		if ( ! is_wp_error( $errors ) ) {
			return;
		}

		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'Partytown Web Worker Module cannot be loaded.', 'performance-lab' ); ?></strong>
				<ul>
				<?php foreach ( array_keys( $errors->errors ) as $error_code ) : ?>
					<?php foreach ( $errors->get_error_messages( $error_code ) as $message ) : ?>
						<li>
							<?php echo wp_kses_post( $message ); ?>
						</li>
					<?php endforeach; ?>
				<?php endforeach; ?>
				</ul>
			</p>
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'perflab_partytown_web_worker_errors_admin_notice' );

return static function () {
	return is_wp_error( perflab_partytown_web_worker_can_load() ) ? new WP_Error(
		'partytown_web_worker_cannot_load',
		__( 'Partytown Web Worker Module cannot be loaded. Please check the admin notices for more information.', 'performance-lab' )
	) : true;
};
