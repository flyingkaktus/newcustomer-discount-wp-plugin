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
     * Standard-Präfix für Gutscheincodes
     *
     * @var string
     */
    private $prefix = 'NL';

    /**
     * Länge des Gutscheincodes (ohne Präfix)
     *
     * @var int
     */
    private $code_length = 6;

    /**
     * Verfügbare Zeichen für Gutscheincodes
     *
     * @var string
     */
    private $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

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
    }

    /**
     * Generiert einen einzigartigen Gutscheincode
     *
     * @return string
     */
    public function generate_unique_code() {
        do {
            $code = $this->prefix;
            for ($i = 0; $i < $this->code_length; $i++) {
                $code .= $this->characters[rand(0, strlen($this->characters) - 1)];
            }
            $exists = $this->coupon_exists($code);
        } while ($exists);

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
            $settings = wp_parse_args($settings, $this->default_coupon_settings);
            $code = $this->generate_unique_code();

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

            $this->set_coupon_meta($coupon_id, $email, $settings);

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
            ]
        ];

        $args = wp_parse_args($args, $defaults);
        $coupons = get_posts($args);

        return array_map(function($coupon) {
            return [
                'id' => $coupon->ID,
                'code' => $coupon->post_title,
                'email' => get_post_meta($coupon->ID, '_ncd_customer_email', true),
                'created' => get_post_meta($coupon->ID, '_ncd_creation_date', true),
                'status' => $this->get_coupon_status($coupon->post_title)
            ];
        }, $coupons);
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
}