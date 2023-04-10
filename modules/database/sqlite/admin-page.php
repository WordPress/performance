<?php
/**
 * Functions for the admin page of the plugin.
 *
 * @since 2.2.0
 * @package performance-lab
 */

/**
 * Add an admin menu page.
 *
 * @since 2.2.0
 */
function sqlite_add_admin_menu() {
	add_options_page(
		__( 'SQLite integration', 'performance-lab' ),
		__( 'SQLite integration', 'performance-lab' ),
		'manage_options',
		'sqlite-integration',
		'sqlite_integration_admin_screen'
	);
}
add_action( 'admin_menu', 'sqlite_add_admin_menu' );

/**
 * The admin page contents.
 */
function sqlite_integration_admin_screen() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'SQLite integration.', 'performance-lab' ); ?></h1>
	</div>
	<!-- Set the wrapper width to 50em, to improve readability. -->
	<div style="max-width:50em;">
		<?php if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) ) : ?>
			<div class="notice notice-success">
				<p><?php esc_html_e( 'SQLite is enabled.', 'performance-lab' ); ?></p>
			</div>
			<p>
				<?php
					printf(
						/* translators: 1: Admin URL to deactivate the module, 2: db.php drop-in path, */
						__( 'The SQLite drop-in is enabled. To disable it and get back to your previous, MySQL database, you can <a href="%1$s">deactivate the plugin</a>. Alternatively, you can manually delete the %2$s file from your server.', 'performance-lab' ),
						esc_url( admin_url( 'plugins.php' ) ),
						'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '/db.php</code>'
					);
				?>
			</p>
		<?php else : ?>
			<?php if ( ! class_exists( 'SQLite3' ) ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'We detected that the SQLite3 class is missing from your server. Please make sure that SQLite is enabled in your PHP installation before proceeding.', 'performance-lab' ); ?></p>
				</div>
			<?php elseif ( file_exists( WP_CONTENT_DIR . '/db.php' ) && ! defined( 'SQLITE_DB_DROPIN_VERSION' ) ) : ?>
				<div class="notice notice-error">
					<p>
						<?php
						printf(
							/* translators: %s: db.php drop-in path */
							esc_html__( 'The SQLite plugin cannot be activated because a different %s drop-in already exists.', 'performance-lab' ),
							'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '/db.php</code>'
						);
						?>
					</p>
				</div>
			<?php elseif ( ! is_writable( WP_CONTENT_DIR ) ) : ?>
				<div class="notice notice-error">
					<p>
						<?php
						printf(
							/* translators: %s: db.php drop-in path */
							esc_html__( 'The SQLite plugin cannot be activated because the %s directory is not writable.', 'performance-lab' ),
							'<code>' . esc_html( basename( WP_CONTENT_DIR ) ) . '</code>'
						);
						?>
					</p>
				</div>
			<?php else : ?>
				<div class="notice notice-success">
					<p><?php esc_html_e( 'All checks completed successfully, your site can use an SQLite database. You can proceed with the installation.', 'performance-lab' ); ?></p>
				</div>
				<h2><?php esc_html_e( 'Important note', 'performance-lab' ); ?></h2>
				<p><?php esc_html_e( 'This plugin will switch to a separate database and install WordPress in it. You will need to reconfigure your site, and start with a fresh site. Disabling the plugin you will get back to your previous MySQL database, with all your previous data intact.', 'performance-lab' ); ?></p>
				<p><?php esc_html_e( 'By clicking the button below, you will be redirected to the WordPress installation screen to setup your new database', 'performance-lab' ); ?></p>

				<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=sqlite-integration&confirm-install' ), 'sqlite-install' ) ); ?>"><?php esc_html_e( 'Install SQLite database', 'performance-lab' ); ?></a>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Adds a link to the admin bar.
 *
 * @since n.e.x.t
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param WP_Admin_Bar $admin_bar The admin bar object.
 */
function sqlite_plugin_adminbar_item( $admin_bar ) {
	global $wpdb;

	if ( defined( 'SQLITE_DB_DROPIN_VERSION' ) && defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) {
		$title = '<span style="color:#46B450;">' . __( 'Database: SQLite', 'performance-lab' ) . '</span>';
	} elseif ( stripos( $wpdb->db_server_info(), 'maria' ) !== false ) {
		$title = '<span style="color:#DC3232;">' . __( 'Database: MariaDB', 'performance-lab' ) . '</span>';
	} else {
		$title = '<span style="color:#DC3232;">' . __( 'Database: MySQL', 'performance-lab' ) . '</span>';
	}

	$args = array(
		'id'     => 'sqlite-db-integration',
		'parent' => 'top-secondary',
		'title'  => $title,
		'href'   => esc_url( admin_url( 'options-general.php?page=sqlite-integration' ) ),
		'meta'   => false,
	);
	$admin_bar->add_node( $args );
}
add_action( 'admin_bar_menu', 'sqlite_plugin_adminbar_item', 999 );
