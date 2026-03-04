<?php
/**
 * Plugin Name: Multi Pixel Facebook Conversions
 * Plugin URI: https://github.com/arturoromano974/facebook-php-business-sdk
 * Description: Envie eventos de conversão para múltiplos Facebook Pixels simultaneamente com geotargeting automático e rastreamento em tempo real.
 * Version: 1.0.0
 * Author: Arturo Romano
 * Author URI: https://github.com/arturoromano974
 * License: Facebook Platform License
 * License URI: https://github.com/facebook/facebook-php-business-sdk/blob/main/LICENSE
 * Text Domain: multi-pixel-fb
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 *
 * @package MultiPixelFB
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define( 'MPFB_VERSION', '1.0.0' );
define( 'MPFB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MPFB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MPFB_PLUGIN_FILE', __FILE__ );
define( 'MPFB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Load Composer autoloader
if ( file_exists( MPFB_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once MPFB_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    add_action( 'admin_notices', function() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Multi Pixel FB: Por favor, execute "composer install" no diretório do plugin.', 'multi-pixel-fb' ); ?></p>
        </div>
        <?php
    });
    return;
}

use MultiPixelFB\Admin\SettingsPage;
use MultiPixelFB\Admin\PixelManager;
use MultiPixelFB\Frontend\RestController;
use MultiPixelFB\Frontend\Shortcodes;
use MultiPixelFB\Integrations\WooCommerceIntegration;
use MultiPixelFB\Utils\Logger;

/**
 * Main Plugin Class
 */
final class Multi_Pixel_FB {

    /**
     * Plugin instance
     *
     * @var Multi_Pixel_FB
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return Multi_Pixel_FB
     */
    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Load plugin textdomain
        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );

        // Initialize components
        add_action( 'plugins_loaded', array( $this, 'init_components' ) );

        // Enqueue admin assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Enqueue frontend assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

        // Register activation/deactivation hooks
        register_activation_hook( MPFB_PLUGIN_FILE, array( $this, 'activate' ) );
        register_deactivation_hook( MPFB_PLUGIN_FILE, array( $this, 'deactivate' ) );
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'multi-pixel-fb',
            false,
            dirname( MPFB_PLUGIN_BASENAME ) . '/languages'
        );
    }

    /**
     * Initialize plugin components
     */
    public function init_components() {
        // Initialize admin settings
        if ( is_admin() ) {
            SettingsPage::init();
        }

        // Initialize REST API
        RestController::init();

        // Initialize shortcodes
        Shortcodes::init();

        // Initialize WooCommerce integration if WooCommerce is active
        if ( class_exists( 'WooCommerce' ) ) {
            WooCommerceIntegration::init();
        }

        // Initialize logger
        Logger::init();
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our settings page
        if ( 'settings_page_multi-pixel-fb' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'mpfb-admin',
            MPFB_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MPFB_VERSION
        );

        wp_enqueue_script(
            'mpfb-admin',
            MPFB_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            MPFB_VERSION,
            true
        );

        wp_localize_script(
            'mpfb-admin',
            'mpfbAdmin',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'mpfb_admin_nonce' ),
                'strings' => array(
                    'confirmDelete' => __( 'Tem certeza que deseja excluir este pixel?', 'multi-pixel-fb' ),
                    'saved' => __( 'Configurações salvas com sucesso!', 'multi-pixel-fb' ),
                    'error' => __( 'Erro ao salvar configurações.', 'multi-pixel-fb' ),
                ),
            )
        );
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_frontend_assets() {
        // Only enqueue if user has consented (check consent option)
        $consent_enabled = get_option( 'mpfb_require_consent', '1' );
        $user_consented = isset( $_COOKIE['mpfb_consent'] ) && '1' === $_COOKIE['mpfb_consent'];

        if ( '1' === $consent_enabled && ! $user_consented ) {
            return;
        }

        wp_enqueue_script(
            'mpfb-frontend',
            MPFB_PLUGIN_URL . 'assets/js/frontend.js',
            array(),
            MPFB_VERSION,
            true
        );

        wp_localize_script(
            'mpfb-frontend',
            'mpfbFrontend',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'restUrl' => rest_url( 'multipixel-fb/v1' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
            )
        );
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        if ( false === get_option( 'mpfb_pixels' ) ) {
            update_option( 'mpfb_pixels', array() );
        }

        if ( false === get_option( 'mpfb_execution_mode' ) ) {
            update_option( 'mpfb_execution_mode', 'parallel' );
        }

        if ( false === get_option( 'mpfb_require_consent' ) ) {
            update_option( 'mpfb_require_consent', '1' );
        }

        if ( false === get_option( 'mpfb_enable_logging' ) ) {
            update_option( 'mpfb_enable_logging', '0' );
        }

        if ( false === get_option( 'mpfb_woocommerce_enabled' ) ) {
            update_option( 'mpfb_woocommerce_enabled', '1' );
        }

        // Flush rewrite rules
        flush_rewrite_rules();

        // Log activation
        Logger::log( 'Plugin activated', 'info' );
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();

        // Log deactivation
        Logger::log( 'Plugin deactivated', 'info' );
    }
}

/**
 * Initialize the plugin
 */
function multi_pixel_fb() {
    return Multi_Pixel_FB::instance();
}

// Start the plugin
multi_pixel_fb();
