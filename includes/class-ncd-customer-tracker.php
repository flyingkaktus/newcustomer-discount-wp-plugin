<?php
/**
 * Customer Tracker Class
 *
 * Verwaltet das Tracking von Neukunden und deren Rabatt-Status
 *
 * @package NewCustomerDiscount
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class NCD_Customer_Tracker
{
    /**
     * Name der Datenbank-Tabelle
     *
     * @var string
     */
    private static $table_name;

    /**
     * Gibt den Tabellennamen zurück
     *
     * @return string
     */
    private static function get_table_name()
    {
        if (self::$table_name === null) {
            global $wpdb;
            self::$table_name = $wpdb->prefix . 'customer_discount_tracking';
        }
        return self::$table_name;
    }

    /**
     * Constructor
     */
    public function __construct()
    {
        add_action('wp_scheduled_delete', [$this, 'cleanup_old_entries']);
    }

    /**
     * Plugin-Aktivierung
     *
     * @return void
     */
    public static function activate()
    {
        self::create_database_table();
        wp_schedule_event(time(), 'daily', 'cleanup_tracking_entries');
    }

    /**
     * Plugin-Deaktivierung
     *
     * @return void
     */
    public static function deactivate()
    {
        wp_clear_scheduled_hook('cleanup_tracking_entries');
    }

    /**
     * Erstellt die Tracking-Tabelle in der Datenbank
     *
     * @return void
     */
    private static function create_database_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS " . self::get_table_name() . " (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            customer_email varchar(255) NOT NULL,
            customer_first_name varchar(255),
            customer_last_name varchar(255),
            discount_email_sent datetime DEFAULT NULL,
            coupon_code varchar(10),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            status enum('pending', 'sent', 'used', 'expired') DEFAULT 'pending',
            PRIMARY KEY (id),
            UNIQUE KEY customer_email (customer_email),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Prüft ob ein Kunde ein Neukunde ist
     *
     * @param string $email E-Mail-Adresse des Kunden
     * @param string $cutoff_date Optional. Stichtag für die Prüfung
     * @return bool
     */
    // In class-ncd-customer-tracker.php
    public function is_new_customer($email)
    {
        global $wpdb;

        $cutoff_date = get_option('ncd_cutoff_date', '2024-01-01');
        $max_orders = get_option('ncd_order_count', 0);

        $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->prefix}posts as p
        JOIN {$wpdb->prefix}postmeta as pm ON p.ID = pm.post_id
        WHERE p.post_type = 'shop_order'
        AND p.post_date < %s
        AND pm.meta_key = '_billing_email'
        AND pm.meta_value = %s
    ", $cutoff_date, $email));

        return $count <= $max_orders;
    }

    /**
     * Fügt einen neuen Kunden zum Tracking hinzu
     *
     * @param string $email E-Mail-Adresse
     * @param string $first_name Vorname
     * @param string $last_name Nachname
     * @return int|false ID des Eintrags oder false bei Fehler
     */
    public function add_customer($email, $first_name = '', $last_name = '')
    {
        global $wpdb;

        try {
            $result = $wpdb->insert(
                self::get_table_name(),
                [
                    'customer_email' => $email,
                    'customer_first_name' => $first_name,
                    'customer_last_name' => $last_name,
                    'status' => 'pending'
                ],
                ['%s', '%s', '%s', '%s']
            );

            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }

            return $wpdb->insert_id;
        } catch (Exception $e) {
            $this->log_error('Failed to add customer', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Aktualisiert den Status eines Kunden
     *
     * @param string $email E-Mail-Adresse
     * @param string $status Neuer Status
     * @param string $coupon_code Optional. Gutscheincode
     * @return bool
     */
    public function update_customer_status($email, $status, $coupon_code = '')
    {
        global $wpdb;

        $data = [
            'status' => $status,
            'updated_at' => current_time('mysql')
        ];

        if ($status === 'sent' && !empty($coupon_code)) {
            $data['discount_email_sent'] = current_time('mysql');
            $data['coupon_code'] = $coupon_code;
        }

        return $wpdb->update(
            self::get_table_name(),
            $data,
            ['customer_email' => $email],
            ['%s', '%s'],
            ['%s']
        ) !== false;
    }

    /**
     * Holt Kundeninformationen
     *
     * @param array $args Query-Argumente
     * @return array
     */
    public function get_customers($args = [])
    {
        global $wpdb;

        $defaults = [
            'days' => 30,
            'status' => '',
            'only_new' => false,
            'orderby' => 'post_date',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        ];

        $args = wp_parse_args($args, $defaults);

        // Hole direkt die WooCommerce Bestellungen
        $orders = $wpdb->get_results($wpdb->prepare("
            SELECT 
                o.ID as order_id,
                MAX(CASE WHEN pm.meta_key = '_billing_email' THEN pm.meta_value END) as customer_email,
                MAX(CASE WHEN pm.meta_key = '_billing_first_name' THEN pm.meta_value END) as customer_first_name,
                MAX(CASE WHEN pm.meta_key = '_billing_last_name' THEN pm.meta_value END) as customer_last_name,
                o.post_date as created_at
            FROM {$wpdb->prefix}posts o
            JOIN {$wpdb->prefix}postmeta pm ON o.ID = pm.post_id
            WHERE o.post_type = 'shop_order'
            AND o.post_status IN ('wc-completed', 'wc-processing')
            AND o.post_date >= DATE_SUB(NOW(), INTERVAL %d DAY)
            AND pm.meta_key IN ('_billing_email', '_billing_first_name', '_billing_last_name')
            GROUP BY o.ID
            ORDER BY o.post_date DESC
            LIMIT %d OFFSET %d
        ", $args['days'], $args['limit'], $args['offset']), ARRAY_A); // ARRAY_A hinzugefügt

        // Ergänze Tracking-Informationen
        $processed_orders = [];
        foreach ($orders as $order) {
            $tracking = $wpdb->get_row($wpdb->prepare("
                SELECT discount_email_sent, coupon_code 
                FROM " . self::get_table_name() . "
                WHERE customer_email = %s
            ", $order['customer_email']), ARRAY_A); // ARRAY_A hinzugefügt

            if ($tracking) {
                $order['discount_email_sent'] = $tracking['discount_email_sent'];
                $order['coupon_code'] = $tracking['coupon_code'];
            } else {
                $order['discount_email_sent'] = null;
                $order['coupon_code'] = null;
            }

            $processed_orders[] = $order;
        }

        return $processed_orders;
    }

    /**
     * Bereinigt alte Einträge
     *
     * @return int Anzahl der gelöschten Einträge
     */
    public function cleanup_old_entries()
    {
        global $wpdb;

        return $wpdb->query($wpdb->prepare("
            DELETE FROM " . self::get_table_name() . "
            WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)
            AND (status = 'used' OR status = 'expired')
        ", 365)); // Einträge älter als 1 Jahr
    }

    /**
     * Loggt Fehler für Debugging
     *
     * @param string $message Fehlermeldung
     * @param array $context Zusätzliche Kontext-Informationen
     * @return void
     */
    private function log_error($message, $context = [])
    {
        if (WP_DEBUG) {
            error_log(sprintf(
                '[NewCustomerDiscount] Customer Tracker Error: %s | Context: %s',
                $message,
                json_encode($context)
            ));
        }
    }

    /**
     * Gibt die Tabellen-Statistiken zurück
     *
     * @return array
     */
    public function get_statistics()
    {
        global $wpdb;

        // Prüfe ob Tabelle existiert
        $table_exists = $wpdb->get_var(
            "SHOW TABLES LIKE '" . self::get_table_name() . "'"
        ) === self::get_table_name();

        if (!$table_exists) {
            return [
                'total' => 0,
                'pending' => 0,
                'sent' => 0,
                'used' => 0,
                'expired' => 0
            ];
        }

        return [
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM " . self::get_table_name()),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM " . self::get_table_name() . " WHERE status = 'pending'"),
            'sent' => $wpdb->get_var("SELECT COUNT(*) FROM " . self::get_table_name() . " WHERE status = 'sent'"),
            'used' => $wpdb->get_var("SELECT COUNT(*) FROM " . self::get_table_name() . " WHERE status = 'used'"),
            'expired' => $wpdb->get_var("SELECT COUNT(*) FROM " . self::get_table_name() . " WHERE status = 'expired'")
        ];
    }
}