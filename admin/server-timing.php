<?php
/**
 * Server-Timing API admin integration file.
 *
 * @package performance-lab
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Do not add any of the hooks if Server-Timing is disabled.
if ( defined( 'PERFLAB_DISABLE_SERVER_TIMING' ) && PERFLAB_DISABLE_SERVER_TIMING ) {
	return;
}

/**
 * Adds the Server-Timing page to the Tools menu.
 *
 * @since n.e.x.t
 */
function perflab_add_server_timing_page() {
	$hook_suffix = add_management_page(
		__( 'Server-Timing', 'performance-lab' ),
		__( 'Server-Timing', 'performance-lab' ),
		'manage_options',
		PERFLAB_SERVER_TIMING_SCREEN,
		'perflab_render_server_timing_page'
	);

	// Add the following hooks only if the screen was successfully added.
	if ( false !== $hook_suffix ) {
		add_action( "load-{$hook_suffix}", 'perflab_load_server_timing_page' );
	}

	return $hook_suffix;
}
add_action( 'admin_menu', 'perflab_add_server_timing_page' );

/**
 * Initializes settings sections and fields for the Server-Timing page.
 *
 * @since n.e.x.t
 */
function perflab_load_server_timing_page() {
	add_settings_section(
		'output-buffering',
		__( 'Output Buffering', 'performance-lab' ),
		'perflab_render_server_timing_page_output_buffer_checkbox',
		PERFLAB_SERVER_TIMING_SCREEN
	);

	add_settings_section(
		'benchmarking',
		__( 'Benchmarking', 'performance-lab' ),
		static function() {
			?>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: Server-Timing */
						__( 'In this section, you can provide hook names to include measurements for them in the %s header.', 'performance-lab' ),
						'<code>Server-Timing</code>'
					),
					array( 'code' => array() )
				);
				?>
				<br>
				<?php
				echo wp_kses(
					__( 'For any hook name provided, the <strong>cumulative duration between all callbacks</strong> attached to the hook is measured, in milliseconds.', 'performance-lab' ),
					array( 'strong' => array() )
				);
				if ( ! perflab_server_timing_use_output_buffer() ) {
					?>
					<br>
					<?php
					echo wp_kses(
						sprintf(
							/* translators: 1: Server-Timing, 2: template_include, 3: anchor link */
							__( 'Since the %1$s header is sent before the template is loaded, only hooks before the %2$s filter can be measured. Enable <a href="%3$s">Output Buffering</a> to measure hooks during template rendering.', 'performance-lab' ),
							'<code>Server-Timing</code>',
							'<code>template_include</code>',
							esc_url( '#server_timing_output_buffering' )
						),
						array(
							'code' => array(),
							'a'    => array( 'href' => true ),
						)
					);
				}
				?>
			</p>
			<?php
		},
		PERFLAB_SERVER_TIMING_SCREEN
	);

	/*
	 * For all settings fields, the field slug, option sub key, and label
	 * suffix have to match for the rendering in the callback to be
	 * semantically correct.
	 */
	add_settings_field(
		'benchmarking_actions',
		__( 'Actions', 'performance-lab' ),
		static function() {
			perflab_render_server_timing_page_hooks_field( 'benchmarking_actions' );
		},
		PERFLAB_SERVER_TIMING_SCREEN,
		'benchmarking',
		array( 'label_for' => 'server_timing_benchmarking_actions' )
	);
	add_settings_field(
		'benchmarking_filters',
		__( 'Filters', 'performance-lab' ),
		static function() {
			perflab_render_server_timing_page_hooks_field( 'benchmarking_filters' );
		},
		PERFLAB_SERVER_TIMING_SCREEN,
		'benchmarking',
		array( 'label_for' => 'server_timing_benchmarking_filters' )
	);
}

/**
 * Renders the Server-Timing page.
 *
 * @since n.e.x.t
 */
function perflab_render_server_timing_page() {
	?>
	<div class="wrap">
		<?php settings_errors(); ?>
		<h1>
			<?php esc_html_e( 'Server-Timing', 'performance-lab' ); ?>
		</h1>

		<form action="options.php" method="post">
			<?php settings_fields( PERFLAB_SERVER_TIMING_SCREEN ); ?>
			<?php do_settings_sections( PERFLAB_SERVER_TIMING_SCREEN ); ?>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/**
 * Renders a hooks field for the given Server-Timing option.
 *
 * @since n.e.x.t
 *
 * @param string $slug Slug of the field and sub-key in the Server-Timing option.
 */
function perflab_render_server_timing_page_hooks_field( $slug ) {
	$options = (array) get_option( PERFLAB_SERVER_TIMING_SETTING, array() );

	// Value for the sub-key is an array of hook names.
	$value = '';
	if ( isset( $options[ $slug ] ) ) {
		$value = implode( "\n", $options[ $slug ] );
	}

	$field_id       = "server_timing_{$slug}";
	$field_name     = PERFLAB_SERVER_TIMING_SETTING . '[' . $slug . ']';
	$description_id = "{$field_id}_description";

	?>
	<textarea
		id="<?php echo esc_attr( $field_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		aria-describedby="<?php echo esc_attr( $description_id ); ?>"
		class="large-text code"
		rows="8"
	><?php echo esc_textarea( $value ); ?></textarea>
	<p id="<?php echo esc_attr( $description_id ); ?>" class="description">
		<?php esc_html_e( 'Enter a single hook name per line.', 'performance-lab' ); ?>
	</p>
	<?php
}

/**
 * Renders a checkbox for enabling output buffering for Server-Timing.
 *
 * @since n.e.x.t
 */
function perflab_render_server_timing_page_output_buffer_checkbox() {
	$slug           = 'output_buffering';
	$field_id       = "server_timing_{$slug}";
	$field_name     = PERFLAB_SERVER_TIMING_SETTING . '[' . $slug . ']';
	$description_id = "{$field_id}_description";
	$has_filter     = has_filter( 'perflab_server_timing_use_output_buffer' );
	$is_enabled     = perflab_server_timing_use_output_buffer();

	?>
	<style>
	/* The following styles are copied from core where they normally include `.form-table td` in the selectors. */
	.output-buffering-field {
		margin-bottom: 9px;
		padding-bottom: 15px;
		line-height: 1.3;
		vertical-align: middle;
		font-size: 14px;
	}
	.output-buffering-field label,
	.output-buffering-field p {
		line-height: 1.4;
	}
	.output-buffering-field label {
		margin: 0.35em 0 0.5em !important;
		display: inline-block;
		vertical-align: middle;
	}
	.output-buffering-field p {
		font-size: 14px;
	}
	</style>
	<div class="output-buffering-field">
		<input
			type="checkbox"
			id="<?php echo esc_attr( $field_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			aria-describedby="<?php echo esc_attr( $description_id ); ?>"
			<?php disabled( $has_filter ); ?>
			<?php checked( $is_enabled ); ?>
		>
		<label for="<?php echo esc_attr( $field_id ); ?>">
			<?php esc_html_e( 'Enable output buffering of template rendering.', 'performance-lab' ); ?>
		</label>
		<p id="<?php echo esc_attr( $description_id ); ?>" class="description">
			<?php if ( $has_filter ) : ?>
				<?php if ( $is_enabled ) : ?>
					<?php
					echo wp_kses(
						__( 'Output buffering has been forcibly enabled via the <code>perflab_server_timing_use_output_buffer</code> filter.', 'performance-lab' ),
						array( 'code' => array() )
					);
					?>
				<?php else : ?>
					<?php
					echo wp_kses(
						__( 'Output buffering has been forcibly disabled via the <code>perflab_server_timing_use_output_buffer</code> filter.', 'performance-lab' ),
						array( 'code' => array() )
					);
					?>
				<?php endif; ?>
			<?php endif; ?>
			<?php esc_html_e( 'This is needed to capture metrics after headers have been sent and while the template is being rendered. Note that output buffering may possibly cause an increase in TTFB if the response would be flushed multiple times.', 'performance-lab' ); ?>
		</p>
	</div>
	<?php
}
