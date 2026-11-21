<?php
/**
 * Site Health tab integration.
 *
 * @package ai-performance-advisor
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the AI Performance Advisor tab on the Site Health screen.
 *
 * @since 1.0.0
 *
 * @param string[]|mixed $tabs Associative array of tab labels keyed by slug.
 * @return string[]|mixed Amended tabs.
 */
function aipa_add_site_health_tab( $tabs ) {
	if ( ! is_array( $tabs ) ) {
		return $tabs;
	}
	$tabs['ai-performance-advisor'] = _x( 'AI Performance Advisor', 'Site Health navigation title', 'ai-performance-advisor' );
	return $tabs;
}

/**
 * Renders the content of the AI Performance Advisor tab.
 *
 * @since 1.0.0
 *
 * @param string $tab The slug of the currently displayed tab.
 */
function aipa_render_site_health_tab( string $tab ): void {
	if ( 'ai-performance-advisor' !== $tab ) {
		return;
	}

	?>
	<div class="health-check-body aipa-tab">
		<h2><?php esc_html_e( 'AI Performance Advisor', 'ai-performance-advisor' ); ?></h2>

		<?php if ( ! aipa_is_ai_available() ) : ?>
			<div class="notice notice-warning inline">
				<p>
					<?php
					$aipa_connect_message = sprintf(
						/* translators: %s: URL to the connectors settings screen. */
						__( 'AI is not available yet. To get recommendations, connect an AI provider that supports text generation in the <a href="%s">AI connectors settings</a>.', 'ai-performance-advisor' ),
						esc_url( admin_url( 'options-connectors.php' ) )
					);
					echo wp_kses( $aipa_connect_message, array( 'a' => array( 'href' => array() ) ) );
					?>
				</p>
			</div>
		<?php else : ?>
			<p class="description">
				<?php esc_html_e( 'When you start an analysis, the following information about your site is sent to your configured AI provider:', 'ai-performance-advisor' ); ?>
			</p>
			<ul class="ul-disc">
				<?php foreach ( aipa_get_context_registry()->get_available_labels() as $aipa_label ) : ?>
					<li><?php echo esc_html( $aipa_label ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p>
				<button type="button" class="button button-primary" id="aipa-analyze">
					<?php esc_html_e( 'Analyze my site', 'ai-performance-advisor' ); ?>
				</button>
				<span class="spinner" id="aipa-spinner" style="float:none;"></span>
			</p>
			<p class="description">
				<?php esc_html_e( 'This calls your configured AI provider and may incur usage costs. Results are cached so reopening this tab will not run a new analysis.', 'ai-performance-advisor' ); ?>
			</p>
			<div id="aipa-results" aria-live="polite"></div>
		<?php endif; ?>
	</div>
	<?php
}
