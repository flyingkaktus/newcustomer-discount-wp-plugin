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

class NCD_Customer_Tracker {
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
    private static function get_table_name() {
        if (self::$table_name === null) {
            global $wpdb;
            self::$table_name = $wpdb->prefix . 'customer_discount_tracking';
        }
        return self::$table_name;
    }

    /**
     * Constructor
     */
    public function __construct() {
        add_action('wp_scheduled_delete', [$this, 'cleanup_old_entries']);
    }

    /**
     * Plugin-Aktivierung
     *
     * @return void
     */
    public static function activate() {
        self::create_database_table();
        wp_schedule_event(time(), 'daily', 'cleanup_tracking_entries');
    }

    /**
     * Plugin-Deaktivierung
     *
     * @return void
     */
    public static function deactivate() {
        wp_clear_scheduled_hook('cleanup_tracking_entries');
    }

    /**
     * Erstellt die Tracking-Tabelle in der Datenbank
     *
     * @return void
     */
    private static function create_database_table() {
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
    public function is_new_customer($email, $cutoff_date = NEWCUSTOMER_CUTOFF_DATE) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}posts as p
            JOIN {$wpdb->prefix}postmeta as pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_order'
            AND p.post_date < %s
            AND pm.meta_key = '_billing_email'
            AND pm.meta_value = %s
        ", $cutoff_date, $email));
        
        return $count == 0;
    }

    /**
     * Fügt einen neuen Kunden zum Tracking hinzu
     *
     * @param string $email E-Mail-Adresse
     * @param string $first_name Vorname
     * @param string $last_name Nachname
     * @return int|false ID des Eintrags oder false bei Fehler
     */
    public function add_customer($email, $first_name = '', $last_name = '') {
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
    public function update_customer_status($email, $status, $coupon_code = '') {
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
    public function get_customers($args = []) {
        global $wpdb;
        
        $defaults = [
            'days' => 30,
            'status' => '',
            'only_new' => false,
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = ["1=1"];
        $values = [];
        
        if ($args['days'] > 0) {
            $where[] = "created_at >= DATE_SUB(NOW(), INTERVAL %d DAY)";
            $values[] = $args['days'];
        }
        
        if (!empty($args['status'])) {
            $where[] = "status = %s";
            $values[] = $args['status'];
        }
        
        $query = "SELECT * FROM " . self::get_table_name() . "
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY {$args['orderby']} {$args['order']}
                 LIMIT %d OFFSET %d";
        
        $values[] = $args['limit'];
        $values[] = $args['offset'];
        
        $results = $wpdb->get_results(
            $wpdb->prepare($query, $values),
            ARRAY_A
        );
        
        if ($args['only_new']) {
            $results = array_filter($results, function($customer) {
                return $this->is_new_customer($customer['customer_email']);
            });
        }
        
        return $results;
    }

    /**
     * Bereinigt alte Einträge
     *
     * @return int Anzahl der gelöschten Einträge
     */
    public function cleanup_old_entries() {
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
    private function log_error($message, $context = []) {
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
    public function get_statistics() {
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