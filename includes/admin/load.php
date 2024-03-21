<?php
/**
 * Admin integration file.
 *
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Adds the modules page to the Settings menu.
 *
 * @since 1.0.0
 * @since n.e.x.t Renamed to perflab_add_features_page().
 */
function perflab_add_features_page() {
	$hook_suffix = add_options_page(
		__( 'Performance Features', 'performance-lab' ),
		__( 'Performance', 'performance-lab' ),
		'manage_options',
		PERFLAB_SCREEN,
		'perflab_render_settings_page'
	);

	// Add the following hooks only if the screen was successfully added.
	if ( false !== $hook_suffix ) {
		add_action( "load-{$hook_suffix}", 'perflab_load_features_page', 10, 0 );
		add_filter( 'plugin_action_links_' . plugin_basename( PERFLAB_MAIN_FILE ), 'perflab_plugin_action_links_add_settings' );
	}

	return $hook_suffix;
}
add_action( 'admin_menu', 'perflab_add_features_page' );

/**
 * Initializes settings sections and fields for the modules page.
 *
 * @since 1.0.0
 * @since n.e.x.t Renamed to perflab_load_features_page(), and the
 *                $module and $hook_suffix parameters were removed.
 */
function perflab_load_features_page() {
	// Handle script enqueuing for settings page.
	add_action( 'admin_enqueue_scripts', 'perflab_enqueue_modules_page_scripts' );

	// Handle admin notices for settings page.
	add_action( 'admin_notices', 'perflab_plugin_admin_notices' );
}

/**
 * Renders the plugin page.
 *
 * @since 1.0.0
 * @since n.e.x.t Renamed to perflab_render_settings_page().
 */
function perflab_render_settings_page() {
	?>
	<div class="wrap">
		<?php perflab_render_plugins_ui(); ?>
	</div>
	<?php
}

/**
 * Initializes admin pointer.
 *
 * Handles the bootstrapping of the admin pointer.
 * Mainly jQuery code that is self-initialising.
 *
 * @since 1.0.0
 *
 * @param string $hook_suffix The current admin page.
 */
function perflab_admin_pointer( $hook_suffix ) {
	if ( ! in_array( $hook_suffix, array( 'index.php', 'plugins.php' ), true ) ) {
		return;
	}

	// Do not show admin pointer in multisite Network admin or User admin UI.
	if ( is_network_admin() || is_user_admin() ) {
		return;
	}

	$current_user = get_current_user_id();
	$dismissed    = explode( ',', (string) get_user_meta( $current_user, 'dismissed_wp_pointers', true ) );

	if ( in_array( 'perflab-admin-pointer', $dismissed, true ) ) {
		return;
	}

	// Enqueue pointer CSS and JS.
	wp_enqueue_style( 'wp-pointer' );
	wp_enqueue_script( 'wp-pointer' );
	add_action( 'admin_print_footer_scripts', 'perflab_render_pointer', 10, 0 );
}
add_action( 'admin_enqueue_scripts', 'perflab_admin_pointer' );

/**
 * Renders the Admin Pointer.
 *
 * Handles the rendering of the admin pointer.
 *
 * @since 1.0.0
 * @since 2.4.0 Optional arguments were added to make the function reusable for different pointers.
 *
 * @param string $pointer_id Optional. ID of the pointer. Default 'perflab-admin-pointer'.
 * @param array  $args       Optional. Pointer arguments. Supports 'heading' and 'content' entries.
 *                           Defaults are the heading and content for the 'perflab-admin-pointer'.
 */
function perflab_render_pointer( $pointer_id = 'perflab-admin-pointer', $args = array() ) {
	if ( ! isset( $args['heading'] ) ) {
		$args['heading'] = __( 'Performance Lab', 'performance-lab' );
	}
	if ( ! isset( $args['content'] ) ) {
		$args['content'] = sprintf(
			/* translators: %s: settings page link */
			esc_html__( 'You can now test upcoming WordPress performance features. Open %s to individually toggle the performance features.', 'performance-lab' ),
			'<a href="' . esc_url( add_query_arg( 'page', PERFLAB_SCREEN, admin_url( 'options-general.php' ) ) ) . '">' . esc_html__( 'Settings > Performance', 'performance-lab' ) . '</a>'
		);
	}

	$wp_kses_options = array(
		'a' => array(
			'href' => array(),
		),
	);

	?>
	<script id="<?php echo esc_attr( $pointer_id ); ?>" type="text/javascript">
		jQuery( function() {
			// Pointer Options.
			var options = {
				content: '<h3><?php echo esc_js( $args['heading'] ); ?></h3><p><?php echo wp_kses( $args['content'], $wp_kses_options ); ?></p>',
				position: {
					edge:  'left',
					align: 'right',
				},
				pointerClass: 'wp-pointer arrow-top',
				pointerWidth: 420,
				close: function() {
					jQuery.post(
						window.ajaxurl,
						{
							pointer: '<?php echo esc_js( $pointer_id ); ?>',
							action:  'dismiss-wp-pointer',
							_wpnonce: <?php echo wp_json_encode( wp_create_nonce( 'dismiss_pointer' ) ); ?>,
						}
					);
				}
			};

			jQuery( '#menu-settings' ).pointer( options ).pointer( 'open' );
		} );
	</script>
	<?php
}

/**
 * Adds a link to the modules page to the plugin's entry in the plugins list table.
 *
 * This function is only used if the modules page exists and is accessible.
 *
 * @since 1.0.0
 *
 * @see perflab_add_features_page()
 *
 * @param array $links List of plugin action links HTML.
 * @return array Modified list of plugin action links HTML.
 */
function perflab_plugin_action_links_add_settings( $links ) {
	// Add link as the first plugin action link.
	$settings_link = sprintf(
		'<a href="%s">%s</a>',
		esc_url( add_query_arg( 'page', PERFLAB_SCREEN, admin_url( 'options-general.php' ) ) ),
		esc_html__( 'Settings', 'performance-lab' )
	);
	array_unshift( $links, $settings_link );

	return $links;
}

/**
 * Dismisses notification pointer after verifying nonce.
 *
 * This function adds a nonce check before dismissing perflab-admin-pointer
 * It runs before the dismiss-wp-pointer AJAX action is performed.
 *
 * @since 2.3.0
 *
 * @see perflab_render_modules_pointer()
 */
function perflab_dismiss_wp_pointer_wrapper() {
	if ( isset( $_POST['pointer'] ) && 'perflab-admin-pointer' !== $_POST['pointer'] ) {
		// Another plugin's pointer, do nothing.
		return;
	}
	check_ajax_referer( 'dismiss_pointer' );
}
add_action( 'wp_ajax_dismiss-wp-pointer', 'perflab_dismiss_wp_pointer_wrapper', 0 );

/**
 * Callback function to handle admin scripts.
 *
 * @since 2.8.0
 */
function perflab_enqueue_modules_page_scripts() {
	wp_enqueue_script( 'updates' );

	wp_localize_script(
		'updates',
		'_wpUpdatesItemCounts',
		array(
			'settings' => array(
				'totals' => wp_get_update_data(),
			),
		)
	);

	wp_enqueue_script( 'thickbox' );
	wp_enqueue_style( 'thickbox' );

	wp_enqueue_script( 'plugin-install' );

	wp_enqueue_script(
		'perflab-plugin-management',
		plugin_dir_url( __FILE__ ) . 'js/perflab-plugin-management.js',
		array( 'jquery' ),
		'1.0.0',
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
}

/**
 * Callback function hooked to admin_action_perflab_activate_plugin to handle plugin activation.
 *
 * @since 2.8.0
 */
function perflab_activate_plugin() {
	// Do not proceed if plugin query arg is not present.
	if ( empty( $_GET['plugin'] ) ) {
		return;
	}

	$plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );

	check_admin_referer( "perflab_activate_plugin_{$plugin}" );

	// If `$plugin` is a plugin slug rather than a plugin basename, determine the full plugin basename.
	if ( ! str_contains( $plugin, '/' ) ) {
		$plugins = get_plugins( '/' . $plugin );

		if ( empty( $plugins ) ) {
			wp_die( esc_html__( 'Plugin not found.', 'default' ) );
		}

		$plugin_file_names = array_keys( $plugins );
		$plugin            = $plugin . '/' . $plugin_file_names[0];
	}

	if ( ! current_user_can( 'activate_plugin', $plugin ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to activate this plugin.', 'default' ) );
	}

	// Activate the plugin in question and return to prior screen.
	$do_plugin_activation = activate_plugins( $plugin );
	$referer              = wp_get_referer();
	if ( ! is_wp_error( $do_plugin_activation ) ) {
		$referer = add_query_arg(
			array(
				'activate' => true,
			),
			$referer
		);
	}

	if ( wp_safe_redirect( $referer ) ) {
		exit;
	}
}
add_action( 'admin_action_perflab_activate_plugin', 'perflab_activate_plugin' );

/**
 * Callback function hooked to admin_action_perflab_deactivate_plugin to handle plugin deactivation.
 *
 * @since 2.8.0
 */
function perflab_deactivate_plugin() {
	// Do not proceed if plugin query arg is not present.
	if ( empty( $_GET['plugin'] ) ) {
		return;
	}

	// The plugin being deactivated.
	$plugin = sanitize_text_field( wp_unslash( $_GET['plugin'] ) );

	check_admin_referer( "perflab_deactivate_plugin_{$plugin}" );

	if ( ! current_user_can( 'deactivate_plugin', $plugin ) ) {
		wp_die( esc_html__( 'Sorry, you are not allowed to deactivate this plugin.', 'default' ) );
	}

	// Deactivate the plugin in question and return to prior screen.
	$do_plugin_deactivation = deactivate_plugins( $plugin );
	$referer                = wp_get_referer();
	if ( ! is_wp_error( $do_plugin_deactivation ) ) {
		$referer = add_query_arg(
			array(
				'deactivate' => true,
			),
			$referer
		);
	}

	if ( wp_safe_redirect( $referer ) ) {
		exit;
	}
}
add_action( 'admin_action_perflab_deactivate_plugin', 'perflab_deactivate_plugin' );

/**
 * Callback function hooked to admin_notices to render admin notices on the plugin's screen.
 *
 * @since 2.8.0
 */
function perflab_plugin_admin_notices() {
	if ( isset( $_GET['activate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Plugin activated.', 'default' ); ?></p>
		</div>
		<?php
	} elseif ( isset( $_GET['deactivate'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Plugin deactivated.', 'default' ); ?></p>
		</div>
		<?php
	}
}
