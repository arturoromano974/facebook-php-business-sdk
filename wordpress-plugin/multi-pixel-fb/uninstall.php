<?php
/**
 * Uninstall Multi Pixel Facebook Conversions Plugin
 *
 * @package MultiPixelFB
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'mpfb_pixels' );
delete_option( 'mpfb_execution_mode' );
delete_option( 'mpfb_require_consent' );
delete_option( 'mpfb_enable_logging' );
delete_option( 'mpfb_woocommerce_enabled' );
delete_option( 'mpfb_secret_key' );
delete_option( 'mpfb_partner_agent' );

// Delete transients
delete_transient( 'mpfb_pixel_cache' );

// Delete logs (if stored in options)
delete_option( 'mpfb_error_logs' );
delete_option( 'mpfb_activity_logs' );

// Clear any cached data
wp_cache_flush();
