<?php
/**
 * Admin Class
 *
 * Verwaltet alle Admin-bezogenen Funktionalitäten
 *
 * @package NewCustomerDiscount
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class NCD_Admin {
    /**
     * @var NCD_Customer_Tracker
     */
    private $customer_tracker;

    /**
     * @var NCD_Coupon_Generator
     */
    private $coupon_generator;

    /**
     * @var NCD_Email_Sender
     */
    private $email_sender;

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_dependencies();
        $this->init_hooks();
    }

    /**
     * Initialisiert die Abhängigkeiten
     */
    private function init_dependencies() {
        $this->customer_tracker = new NCD_Customer_Tracker();
        $this->coupon_generator = new NCD_Coupon_Generator();
        $this->email_sender = new NCD_Email_Sender();
    }

    /**
     * Initialisiert die WordPress Hooks
     */
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_menu_pages']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_ncd_send_test_email', [$this, 'ajax_send_test_email']);
        add_action('wp_ajax_ncd_send_discount', [$this, 'ajax_send_discount']);
        add_action('admin_notices', [$this, 'display_admin_notices']);
    }

    /**
     * Fügt Menü-Einträge hinzu
     */
    public function add_menu_pages() {
        add_menu_page(
            __('Neukunden', 'newcustomer-discount'),
            __('Neukunden', 'newcustomer-discount'),
            'manage_options',
            'new-customers',
            [$this, 'render_customers_page'],
            'dashicons-groups',
            30
        );

        add_submenu_page(
            'new-customers',
            __('Einstellungen', 'newcustomer-discount'),
            __('Einstellungen', 'newcustomer-discount'),
            'manage_options',
            'new-customers-settings',
            [$this, 'render_settings_page']
        );

        add_submenu_page(
            'new-customers',
            __('Statistiken', 'newcustomer-discount'),
            __('Statistiken', 'newcustomer-discount'),
            'manage_options',
            'new-customers-statistics',
            [$this, 'render_statistics_page']
        );
    }

    /**
     * Lädt Admin Assets
     *
     * @param string $hook Der aktuelle Admin-Seiten-Hook
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'new-customers') === false) {
            return;
        }

        wp_enqueue_style(
            'ncd-admin',
            NCD_PLUGIN_URL . 'assets/css/admin.css',
            [],
            NCD_VERSION
        );

        wp_enqueue_script(
            'ncd-admin',
            NCD_PLUGIN_URL . 'assets/js/admin.js',
            ['jquery'],
            NCD_VERSION,
            true
        );

        wp_localize_script('ncd-admin', 'ncdAdmin', [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ncd-admin-nonce'),
            'messages' => [
                'confirm_send' => __('Möchten Sie wirklich einen Rabattcode an diesen Kunden senden?', 'newcustomer-discount'),
                'confirm_test' => __('Möchten Sie eine Test-E-Mail an diese Adresse senden?', 'newcustomer-discount'),
                'error' => __('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'newcustomer-discount'),
            ]
        ]);
    }

    /**
     * Rendert die Hauptseite
     */
    public function render_customers_page() {
        // Filter-Parameter
        $days = isset($_GET['days_filter']) ? (int)$_GET['days_filter'] : 30;
        $only_new = isset($_GET['only_new']);

        // Hole Kundendaten
        $customers = $this->customer_tracker->get_customers([
            'days' => $days,
            'only_new' => $only_new
        ]);

        // Template laden
        include NCD_PLUGIN_DIR . 'templates/admin/customers-page.php';
    }

    /**
     * Rendert die Einstellungsseite
     */
    public function render_settings_page() {
        if ($this->handle_settings_post()) {
            $this->add_admin_notice(
                __('Einstellungen wurden gespeichert.', 'newcustomer-discount'),
                'success'
            );
        }

        // Template laden
        include NCD_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }

    /**
     * Rendert die Statistikseite
     */
    public function render_statistics_page() {
        $stats = [
            'customers' => $this->customer_tracker->get_statistics(),
            'coupons' => $this->get_coupon_statistics(),
            'emails' => $this->get_email_statistics()
        ];

        // Template laden
        include NCD_PLUGIN_DIR . 'templates/admin/statistics-page.php';
    }

    /**
     * Verarbeitet AJAX Test-E-Mail Anfrage
     */
    public function ajax_send_test_email() {
        check_ajax_referer('ncd-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung.', 'newcustomer-discount')]);
        }

        $email = sanitize_email($_POST['email']);
        if (!is_email($email)) {
            wp_send_json_error(['message' => __('Ungültige E-Mail-Adresse.', 'newcustomer-discount')]);
        }

        $result = $this->email_sender->send_test_email($email);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => sprintf(
                __('Test-E-Mail wurde an %s gesendet.', 'newcustomer-discount'),
                $email
            )
        ]);
    }

    /**
     * Verarbeitet AJAX Rabatt-E-Mail Anfrage
     */
    public function ajax_send_discount() {
        check_ajax_referer('ncd-admin-nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung.', 'newcustomer-discount')]);
        }

        $email = sanitize_email($_POST['email']);
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);

        // Erstelle Gutschein
        $coupon = $this->coupon_generator->create_coupon($email);
        if (is_wp_error($coupon)) {
            wp_send_json_error(['message' => $coupon->get_error_message()]);
        }

        // Sende E-Mail
        $result = $this->email_sender->send_discount_email($email, [
            'coupon_code' => $coupon['code'],
            'first_name' => $first_name,
            'last_name' => $last_name
        ]);

        if (is_wp_error($result)) {
            // Lösche Gutschein bei E-Mail-Fehler
            $this->coupon_generator->deactivate_coupon($coupon['code']);
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Aktualisiere Tracking
        $this->customer_tracker->update_customer_status($email, 'sent', $coupon['code']);

        wp_send_json_success([
            'message' => sprintf(
                __('Rabattcode %s wurde an %s gesendet.', 'newcustomer-discount'),
                $coupon['code'],
                $email
            )
        ]);
    }

    /**
     * Verarbeitet POST-Anfragen der Einstellungsseite
     *
     * @return bool
     */
    private function handle_settings_post() {
        if (!isset($_POST['ncd_settings_nonce'])) {
            return false;
        }

        check_admin_referer('ncd_settings', 'ncd_settings_nonce');

        if (isset($_POST['update_logo'])) {
            if (!empty($_FILES['logo_file']['name'])) {
                NCD_Logo_Manager::save_logo($_FILES['logo_file']);
            } elseif (!empty($_POST['logo_base64'])) {
                NCD_Logo_Manager::save_base64($_POST['logo_base64']);
            }
            return true;
        }

        if (isset($_POST['delete_logo'])) {
            NCD_Logo_Manager::delete_logo();
            return true;
        }

        return false;
    }

    /**
     * Fügt Admin-Benachrichtigung hinzu
     *
     * @param string $message Nachricht
     * @param string $type Typ der Nachricht (success, error, warning, info)
     */
    private function add_admin_notice($message, $type = 'success') {
        add_settings_error(
            'ncd_messages',
            'ncd_message',
            $message,
            $type
        );
    }

    /**
     * Zeigt Admin-Benachrichtigungen an
     */
    public function display_admin_notices() {
        settings_errors('ncd_messages');
    }

    /**
     * Holt Gutschein-Statistiken
     *
     * @return array
     */
    private function get_coupon_statistics() {
        $coupons = $this->coupon_generator->get_generated_coupons();
        
        $stats = [
            'total' => count($coupons),
            'used' => 0,
            'expired' => 0,
            'active' => 0
        ];

        foreach ($coupons as $coupon) {
            if (!$coupon['status']['valid']) {
                if ($coupon['status']['is_expired']) {
                    $stats['expired']++;
                } else {
                    $stats['used']++;
                }
            } else {
                $stats['active']++;
            }
        }

        return $stats;
    }

    /**
     * Holt E-Mail-Statistiken
     *
     * @return array
     */
    private function get_email_statistics() {
        $logs = $this->email_sender->get_email_logs();
        
        return [
            'total_sent' => count($logs),
            'last_sent' => !empty($logs) ? $logs[0]->sent_date : null
        ];
    }
}