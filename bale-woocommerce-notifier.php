<?php
/**
 * Plugin Name: Bale WooCommerce Notifier
 * Plugin URI:  https://sport-formol1.com
 * Description: ارسال خودکار جزئیات سفارشات WooCommerce به گروه بله هنگام تغییر وضعیت به Processing به همراه امکان سفارشی‌سازی متن پیام
 * Version:     1.3.0
 * Author:      Sport Formol1
 * Text Domain: bale-woo-notifier
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'BWN_VERSION',     '1.3.0' );
define( 'BWN_OPTIONS_KEY', 'bwn_settings' );
define( 'BWN_BALE_API',    'https://tapi.bale.ai/bot' );
define( 'BWN_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );

// Require modules
require_once BWN_PLUGIN_DIR . 'includes/class-bwn-jalali.php';
require_once BWN_PLUGIN_DIR . 'includes/class-bwn-notifier.php';
require_once BWN_PLUGIN_DIR . 'includes/class-bwn-admin.php';
require_once BWN_PLUGIN_DIR . 'includes/class-bwn-activator.php';

// Register activation hook
register_activation_hook( __FILE__, array( 'BWN_Activator', 'activate' ) );

// Initialize the plugin modules
add_action( 'plugins_loaded', 'bwn_init_plugin' );

function bwn_init_plugin() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="notice notice-error"><p><strong>Bale WooCommerce Notifier:</strong> WooCommerce باید نصب و فعال باشد.</p></div>';
        } );
        return;
    }
    
    // Initialize admin components
    if ( is_admin() ) {
        BWN_Admin::init();
    }
    
    // Initialize notification listeners
    BWN_Notifier::init();
}
