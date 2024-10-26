<?php
/**
 * Plugin Name: Neukunden Rabatt System
 * Plugin URI: https://comingsoon.de
 * Description: Automatisches Rabattsystem für Neukunden mit E-Mail-Versand
 * Version: 1.0.2
 * Author: Maciej Suchowski
 * Author URI: https://comingsoon.de
 * License: GPLv2 or later
 * Text Domain: newcustomer-discount
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package NewCustomerDiscount
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten definieren
define('NCD_VERSION', '1.0.2');
define('NCD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('NCD_PLUGIN_URL', plugin_dir_url(__FILE__));
define('NCD_INCLUDES_DIR', NCD_PLUGIN_DIR . 'includes/');
define('NEWCUSTOMER_CUTOFF_DATE', '2024-01-01 00:00:00');

// Erforderliche Klassen manuell laden
require_once NCD_INCLUDES_DIR . 'class-ncd-customer-tracker.php';
require_once NCD_INCLUDES_DIR . 'class-ncd-email-sender.php';
require_once NCD_INCLUDES_DIR . 'class-ncd-logo-manager.php';
require_once NCD_INCLUDES_DIR . 'class-ncd-admin.php';
require_once NCD_INCLUDES_DIR . 'class-ncd-coupon-generator.php';
require_once NCD_INCLUDES_DIR . 'class-ncd-updater.php';

// GitHub Updater initialisieren
if (is_admin()) {
    new NCD_Updater(__FILE__);
}

/**
 * Plugin Initialisierung
 */
function ncd_init() {
    // Prüfe WooCommerce Abhängigkeit
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'ncd_woocommerce_notice');
        return;
    }
    
    // Lade Textdomain
    load_plugin_textdomain(
        'newcustomer-discount',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
    
    // Initialisiere Admin-Bereich
    if (is_admin()) {
        new NCD_Admin();
    }
}
add_action('plugins_loaded', 'ncd_init');

/**
 * WooCommerce Abhängigkeitshinweis
 */
function ncd_woocommerce_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php _e('Das Neukunden Rabatt System benötigt WooCommerce. Bitte installieren und aktivieren Sie WooCommerce.', 'newcustomer-discount'); ?>
        </p>
    </div>
    <?php
}

/**
 * Aktivierungshook
 */
function ncd_activate() {
    // Erstelle notwendige Datenbanktabellen
    NCD_Customer_Tracker::activate();
    
    // Erstelle E-Mail Log Tabelle
    NCD_Email_Sender::create_log_table();
    
    // Setze Standard-Optionen
    add_option('ncd_delete_all_on_uninstall', false);
    add_option('ncd_discount_amount', 20);
    add_option('ncd_expiry_days', 30);
    add_option('ncd_email_subject', __('Dein persönlicher Neukundenrabatt von comingsoon.de', 'newcustomer-discount'));
    
    // Setze Capabilities
    $role = get_role('administrator');
    if ($role) {
        $role->add_cap('manage_customer_discounts');
    }
    
    // Erstelle Upload-Verzeichnis falls nötig
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/newcustomer-discount';
    if (!file_exists($plugin_upload_dir)) {
        wp_mkdir_p($plugin_upload_dir);
    }
    
    // Setze Version in Datenbank
    update_option('ncd_version', NCD_VERSION);
    
    // Cleanup Schedule erstellen
    if (!wp_next_scheduled('ncd_daily_cleanup')) {
        wp_schedule_event(time(), 'daily', 'ncd_daily_cleanup');
    }
    
    // Cache leeren
    wp_cache_flush();
}
register_activation_hook(__FILE__, 'ncd_activate');

/**
 * Deaktivierungshook
 */
function ncd_deactivate() {
    // Cleanup Schedule entfernen
    wp_clear_scheduled_hook('ncd_daily_cleanup');
    
    // Cache leeren
    wp_cache_flush();
}
register_deactivation_hook(__FILE__, 'ncd_deactivate');

/**
 * Täglicher Cleanup
 */
function ncd_do_daily_cleanup() {
    // Alte Tracking-Einträge bereinigen
    $customer_tracker = new NCD_Customer_Tracker();
    $customer_tracker->cleanup_old_entries();
    
    // Cache leeren
    wp_cache_flush();
}
add_action('ncd_daily_cleanup', 'ncd_do_daily_cleanup');

/**
 * Upgrade-Routine
 */
function ncd_check_version() {
    if (get_option('ncd_version') !== NCD_VERSION) {
        ncd_activate();
    }
}
add_action('plugins_loaded', 'ncd_check_version');

/**
 * Debugging Helper
 */
function ncd_log($message, $context = []) {
    if (WP_DEBUG) {
        error_log(sprintf(
            '[NewCustomerDiscount] %s | Context: %s',
            $message,
            json_encode($context)
        ));
    }
}

// WooCommerce Integration
require_once NCD_PLUGIN_DIR . 'includes/woocommerce-integration.php';