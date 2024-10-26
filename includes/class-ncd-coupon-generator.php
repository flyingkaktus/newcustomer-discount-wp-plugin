<?php
/**
 * Coupon Generator Class
 *
 * Verwaltet die Erstellung und Verwaltung von WooCommerce Gutscheinen
 *
 * @package NewCustomerDiscount
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class NCD_Coupon_Generator {
    /**
     * Verfügbare Zeichen für Gutscheincodes
     *
     * @var array
     */
    private $character_sets = [
        'numbers' => '0123456789',
        'uppercase' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'lowercase' => 'abcdefghijklmnopqrstuvwxyz'
    ];

    /**
     * Aktuell verwendete Zeichen
     *
     * @var string
     */
    private $characters;

    /**
     * Code-Präfix
     *
     * @var string
     */
    private $prefix;

    /**
     * Code-Länge
     *
     * @var int
     */
    private $code_length;

    /**
     * Standard-Gutscheineinstellungen
     *
     * @var array
     */
    private $default_coupon_settings = [
        'discount_type' => 'percent',
        'discount_amount' => 20,
        'individual_use' => 'yes',
        'usage_limit' => 1,
        'expiry_days' => 30
    ];

    /**
     * Constructor
     *
     * @param array $settings Optionale Überschreibung der Standardeinstellungen
     */
    public function __construct($settings = []) {
        $this->default_coupon_settings = wp_parse_args($settings, $this->default_coupon_settings);
        $this->init_code_settings();
    }

    /**
     * Initialisiert Code-Einstellungen
     */
    private function init_code_settings() {
        // Hole gespeicherte Einstellungen
        $this->prefix = get_option('ncd_code_prefix', 'NL');
        $this->code_length = (int)get_option('ncd_code_length', 6);
        $char_types = (array)get_option('ncd_code_chars', ['numbers', 'uppercase']);

        // Erstelle Zeichensatz
        $this->characters = '';
        foreach ($char_types as $type) {
            if (isset($this->character_sets[$type])) {
                $this->characters .= $this->character_sets[$type];
            }
        }

        // Fallback wenn keine Zeichen ausgewählt
        if (empty($this->characters)) {
            $this->characters = $this->character_sets['numbers'] . $this->character_sets['uppercase'];
        }
    }

    /**
     * Generiert einen einzigartigen Gutscheincode
     *
     * @return string
     */
    public function generate_unique_code() {
        $max_attempts = 100; // Verhindert Endlosschleife
        $attempt = 0;

        do {
            $code = $this->prefix;
            for ($i = 0; $i < $this->code_length; $i++) {
                $code .= $this->characters[rand(0, strlen($this->characters) - 1)];
            }
            $exists = $this->coupon_exists($code);
            $attempt++;
        } while ($exists && $attempt < $max_attempts);

        if ($attempt >= $max_attempts) {
            throw new Exception(__('Konnte keinen einzigartigen Code generieren.', 'newcustomer-discount'));
        }

        return $code;
    }

    /**
     * Erstellt einen neuen WooCommerce Gutschein
     *
     * @param string $email E-Mail des Kunden für Tracking
     * @param array $settings Optionale Überschreibung der Standardeinstellungen
     * @return array|WP_Error Array mit Gutscheindaten oder WP_Error bei Fehler
     */
    public function create_coupon($email, $settings = []) {
        try {
            if (!is_email($email)) {
                throw new Exception(__('Ungültige E-Mail-Adresse.', 'newcustomer-discount'));
            }

            $settings = wp_parse_args($settings, $this->default_coupon_settings);
            $code = $this->generate_unique_code();

            // Erstelle WooCommerce Gutschein
            $coupon = [
                'post_title' => $code,
                'post_content' => '',
                'post_status' => 'publish',
                'post_author' => get_current_user_id(),
                'post_type' => 'shop_coupon'
            ];

            $coupon_id = wp_insert_post($coupon, true);

            if (is_wp_error($coupon_id)) {
                throw new Exception($coupon_id->get_error_message());
            }

            // Setze Gutschein-Einstellungen
            $this->set_coupon_meta($coupon_id, $email, $settings);

            // Hole Mindestbestellwert
            $min_amount = get_option('ncd_min_order_amount', 0);
            if ($min_amount > 0) {
                update_post_meta($coupon_id, 'minimum_amount', $min_amount);
            }

            // Hole ausgeschlossene Kategorien
            $excluded_cats = get_option('ncd_excluded_categories', []);
            if (!empty($excluded_cats)) {
                update_post_meta($coupon_id, 'exclude_product_categories', $excluded_cats);
            }

            return [
                'id' => $coupon_id,
                'code' => $code,
                'settings' => $settings,
                'expiry_date' => date('Y-m-d', strtotime("+{$settings['expiry_days']} days"))
            ];

        } catch (Exception $e) {
            $this->log_error('Coupon creation failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);
            return new WP_Error('coupon_creation_failed', $e->getMessage());
        }
    }

    /**
     * Setzt die Meta-Daten für einen Gutschein
     *
     * @param int $coupon_id Post ID des Gutscheins
     * @param string $email E-Mail des Kunden
     * @param array $settings Gutscheineinstellungen
     */
    private function set_coupon_meta($coupon_id, $email, $settings) {
        $meta_data = [
            'discount_type' => $settings['discount_type'],
            'coupon_amount' => $settings['discount_amount'],
            'individual_use' => $settings['individual_use'],
            'usage_limit' => $settings['usage_limit'],
            'expiry_date' => date('Y-m-d', strtotime("+{$settings['expiry_days']} days")),
            'customer_email' => [$email],
            'exclude_sale_items' => 'no',
            'free_shipping' => 'no',
            '_ncd_generated' => 'yes',
            '_ncd_customer_email' => $email,
            '_ncd_creation_date' => current_time('mysql')
        ];

        foreach ($meta_data as $key => $value) {
            update_post_meta($coupon_id, $key, $value);
        }
    }

    /**
     * Prüft ob ein Gutschein bereits existiert
     *
     * @param string $code Gutscheincode
     * @return bool
     */
    public function coupon_exists($code) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type = 'shop_coupon'
            AND post_title = %s
            AND post_status IN ('publish', 'draft', 'pending', 'private')
        ", $code));

        return $count > 0;
    }

    /**
     * Prüft den Status eines Gutscheins
     *
     * @param string $code Gutscheincode
     * @return array Status-Informationen
     */
    public function get_coupon_status($code) {
        $coupon = new WC_Coupon($code);
        
        if (!$coupon->get_id()) {
            return [
                'exists' => false,
                'valid' => false,
                'message' => __('Gutschein existiert nicht.', 'newcustomer-discount')
            ];
        }

        $status = [
            'exists' => true,
            'valid' => true,
            'usage_count' => $coupon->get_usage_count(),
            'usage_limit' => $coupon->get_usage_limit(),
            'expiry_date' => $coupon->get_date_expires() ? $coupon->get_date_expires()->date('Y-m-d') : null,
            'is_expired' => $coupon->get_date_expires() && $coupon->get_date_expires()->getTimestamp() < time(),
            'customer_email' => $coupon->get_email_restrictions(),
            'minimum_amount' => $coupon->get_minimum_amount(),
            'excluded_categories' => $coupon->get_excluded_product_categories()
        ];

        if ($status['is_expired']) {
            $status['valid'] = false;
            $status['message'] = __('Gutschein ist abgelaufen.', 'newcustomer-discount');
        } elseif ($status['usage_count'] >= $status['usage_limit']) {
            $status['valid'] = false;
            $status['message'] = __('Gutschein wurde bereits eingelöst.', 'newcustomer-discount');
        }

        return $status;
    }

    /**
     * Deaktiviert einen Gutschein
     *
     * @param string $code Gutscheincode
     * @return bool
     */
    public function deactivate_coupon($code) {
        $coupon_id = wc_get_coupon_id_by_code($code);
        if (!$coupon_id) {
            return false;
        }

        return wp_update_post([
            'ID' => $coupon_id,
            'post_status' => 'trash'
        ]);
    }

    /**
     * Gibt alle vom Plugin erstellten Gutscheine zurück
     *
     * @param array $args Query-Argumente
     * @return array
     */
    public function get_generated_coupons($args = []) {
        $defaults = [
            'posts_per_page' => -1,
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_ncd_generated',
                    'value' => 'yes'
                ]
            ],
            'orderby' => 'date',
            'order' => 'DESC'
        ];

        $args = wp_parse_args($args, $defaults);
        $coupons = get_posts($args);

        return array_map(function($coupon) {
            return [
                'id' => $coupon->ID,
                'code' => $coupon->post_title,
                'email' => get_post_meta($coupon->ID, '_ncd_customer_email', true),
                'created' => get_post_meta($coupon->ID, '_ncd_creation_date', true),
                'discount_amount' => get_post_meta($coupon->ID, 'coupon_amount', true),
                'minimum_amount' => get_post_meta($coupon->ID, 'minimum_amount', true),
                'expiry_date' => get_post_meta($coupon->ID, 'expiry_date', true),
                'status' => $this->get_coupon_status($coupon->post_title)
            ];
        }, $coupons);
    }

    /**
     * Bereinigt abgelaufene Gutscheine
     *
     * @return int Anzahl der bereinigten Gutscheine
     */
    public function cleanup_expired_coupons() {
        $expired_coupons = get_posts([
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => '_ncd_generated',
                    'value' => 'yes'
                ],
                [
                    'key' => 'expiry_date',
                    'value' => date('Y-m-d'),
                    'compare' => '<',
                    'type' => 'DATE'
                ]
            ],
            'posts_per_page' => -1
        ]);

        $count = 0;
        foreach ($expired_coupons as $coupon) {
            if (wp_update_post(['ID' => $coupon->ID, 'post_status' => 'trash'])) {
                $count++;
            }
        }

        return $count;
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
                '[NewCustomerDiscount] Coupon Generator Error: %s | Context: %s',
                $message,
                json_encode($context)
            ));
        }
    }

    /**
     * Gibt die verfügbaren Zeichensätze zurück
     *
     * @return array
     */
    public function get_available_character_sets() {
        return $this->character_sets;
    }

    /**
     * Validiert einen Gutscheincode
     *
     * @param string $code Zu validierender Code
     * @return bool|WP_Error
     */
    public function validate_code_format($code) {
        // Prüfe Länge
        if (strlen($code) < strlen($this->prefix) + 4 || strlen($code) > strlen($this->prefix) + 12) {
            return new WP_Error(
                'invalid_length',
                __('Ungültige Code-Länge.', 'newcustomer-discount')
            );
        }

        // Prüfe Präfix
        if (strpos($code, $this->prefix) !== 0) {
            return new WP_Error(
                'invalid_prefix',
                __('Ungültiger Code-Präfix.', 'newcustomer-discount')
            );
        }

        // Prüfe Zeichen
        $code_part = substr($code, strlen($this->prefix));
        if (!preg_match('/^[' . preg_quote($this->characters, '/') . ']+$/', $code_part)) {
            return new WP_Error(
                'invalid_characters',
                __('Ungültige Zeichen im Code.', 'newcustomer-discount')
            );
        }

        return true;
    }
}