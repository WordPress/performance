<?php
$metrics = performance_lab_pm_get_site_health_metrics();
?>

<div class="health-check-body health-check-debug-tab hide-if-no-js">
	<h2>
		<?php _e( 'Performance metrics' ); ?>
	</h2>

	<p>
		<?php _e( 'This page shows details about performance metrics of your website.' ); ?>
	</p>
	<p>
		<?php _e( 'If you want to export a handy list of all the information on this page, you can use the button below to copy it to the clipboard. You can then paste it in a text file and save it to your device, or paste it in an email exchange with a support engineer or theme/plugin developer for example.' ); ?>
	</p>


	<?php
	// The formatting helper still needs to be implemented.
	?>
	<div class="site-health-copy-buttons">
		<div class="copy-button-wrapper">
			<button type="button" class="button copy-button" data-clipboard-text="<?php echo esc_attr( $metrics ); ?>">
				<?php _e( 'Copy site info to clipboard' ); ?>
			</button>
			<span class="success hidden" aria-hidden="true"><?php _e( 'Copied!' ); ?></span>
		</div>
	</div>

	<div id="health-check-debug" class="health-check-accordion">
		<?php
		foreach ( $metrics as $section => $details ) {
		if ( ! isset( $details['fields'] ) || empty( $details['fields'] ) ) {
			continue;
		}
		?>

			<h3 class="health-check-accordion-heading">
				<button aria-expanded="true" class="health-check-accordion-trigger" aria-controls="health-check-accordion-block-<?php echo esc_attr( $section ); ?>" type="button">
					<span class="title"><?php echo esc_html( $details['label'] ); ?></span>
					<span class="icon"></span>
				</button>
			</h3>
			<div id="health-check-accordion-block-<?php echo esc_attr( $section ); ?>" class="health-check-accordion-panel">
				<table class="widefat striped health-check-table" role="presentation">
					<tbody>
						<?php
						foreach ( $details['fields'] as $field_name => $field ) {
							printf(
								'<tr><td class="health-check-table-label">%s</td><td>%s</td></tr>',
								esc_html( $field['label'] ),
								esc_html( $field['value'] )
							);
						}
						?>
					</tbody>
				</table>
			</div>
		<? } ?>
	</div>
</div>
