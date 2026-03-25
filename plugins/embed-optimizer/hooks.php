<?php
/**
 * Hook callbacks used for Embed Optimizer.
 *
 * @since 0.1.0
 * @package embed-optimizer
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

add_action( 'init', 'embed_optimizer_add_hooks' );
add_action( 'after_plugin_row_meta', 'embed_optimizer_print_row_meta_install_notice', 20 );

// @codeCoverageIgnoreEnd
