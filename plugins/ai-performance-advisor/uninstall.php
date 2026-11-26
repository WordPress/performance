<?php
/**
 * Uninstaller logic for the AI Performance Advisor plugin.
 *
 * @package ai-performance-advisor
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

// If uninstall.php is not called by WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'aipa_settings' );

// Remove any cached analysis results.
delete_transient( 'aipa_last_analysis' );
delete_transient( 'aipa_pagespeed_cache' );
