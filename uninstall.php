<?php
/**
 * Uninstall Script
 *
 * @package NewCustomerDiscount
 * @since 1.0.0
 */

// Wenn WordPress die Datei nicht direkt aufruft, abbrechen
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Deinstallationsoptionen aus der Datenbank holen
$delete_all = get_option('ncd_delete_all_on_uninstall', false);

// Wenn alle Daten gelöscht werden sollen
if ($delete_all) {
    global $wpdb;

    // Tracking Tabelle löschen
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}customer_discount_tracking");
    
    // Email Log Tabelle löschen
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ncd_email_log");

    // Alle vom Plugin erstellten Gutscheine löschen
    $coupon_ids = $wpdb->get_col(
        "SELECT ID FROM {$wpdb->posts} p
         JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = 'shop_coupon'
         AND pm.meta_key = '_ncd_generated'
         AND pm.meta_value = 'yes'"
    );

    if (!empty($coupon_ids)) {
        foreach ($coupon_ids as $coupon_id) {
            wp_delete_post($coupon_id, true);
        }
    }

    // Plugin Optionen löschen
    $options = [
        'ncd_logo_base64',
        'ncd_delete_all_on_uninstall',
        'ncd_email_subject',
        'ncd_discount_amount',
        'ncd_expiry_days'
    ];

    foreach ($options as $option) {
        delete_option($option);
    }

    // Transients löschen
    $wpdb->query(
        "DELETE FROM {$wpdb->options}
         WHERE option_name LIKE '_transient_ncd_%'
         OR option_name LIKE '_transient_timeout_ncd_%'"
    );
}

// Cache leeren
wp_cache_flush();