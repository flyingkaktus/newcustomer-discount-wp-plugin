<?php
/**
 * Email Sender Class
 *
 * Verwaltet das Erstellen und Versenden von E-Mails
 *
 * @package NewCustomerDiscount
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class NCD_Email_Sender {
    /**
     * E-Mail Template Verzeichnis
     *
     * @var string
     */
    private $template_dir;

    /**
     * Standard E-Mail-Einstellungen
     *
     * @var array
     */
    private $default_settings = [
        'from_name' => '',
        'from_email' => '',
        'subject' => 'Dein persönlicher Neukundenrabatt von comingsoon.de',
        'template' => 'discount-email.php'
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->template_dir = NCD_PLUGIN_DIR . 'templates/email/';
        $this->default_settings['from_name'] = get_bloginfo('name');
        $this->default_settings['from_email'] = get_option('admin_email');

        add_filter('wp_mail_content_type', [$this, 'set_html_content_type']);
    }

    /**
     * Sendet eine Rabatt-E-Mail an einen Kunden
     *
     * @param string $email Empfänger E-Mail
     * @param array $data E-Mail-Daten
     * @param array $settings Optionale E-Mail-Einstellungen
     * @return bool|WP_Error
     */
    public function send_discount_email($email, $data, $settings = []) {
        try {
            if (!is_email($email)) {
                throw new Exception(__('Ungültige E-Mail-Adresse', 'newcustomer-discount'));
            }

            $settings = wp_parse_args($settings, $this->default_settings);
            
            // E-Mail Inhalt generieren
            $content = $this->generate_email_content($data, $settings['template']);
            if (is_wp_error($content)) {
                throw new Exception($content->get_error_message());
            }

            // E-Mail Header
            $headers = $this->generate_email_headers($settings);

            // E-Mail senden
            $sent = wp_mail($email, $settings['subject'], $content, $headers);

            if (!$sent) {
                throw new Exception(__('E-Mail konnte nicht gesendet werden', 'newcustomer-discount'));
            }

            // Log erfolgreichen Versand
            $this->log_email_sent($email, $data);

            return true;

        } catch (Exception $e) {
            $this->log_error('Email sending failed', [
                'email' => $email,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return new WP_Error('email_sending_failed', $e->getMessage());
        }
    }

    /**
     * Generiert den E-Mail-Inhalt aus einem Template
     *
     * @param array $data Template-Daten
     * @param string $template Template-Datei
     * @return string|WP_Error
     */
    private function generate_email_content($data, $template) {
        $template_path = $this->template_dir . $template;

        if (!file_exists($template_path)) {
            return new WP_Error(
                'template_not_found',
                sprintf(__('E-Mail-Template %s nicht gefunden', 'newcustomer-discount'), $template)
            );
        }

        // Daten für Template verfügbar machen
        $logo = NCD_Logo_Manager::get_logo();
        $coupon_code = isset($data['coupon_code']) ? $data['coupon_code'] : '';
        $first_name = isset($data['first_name']) ? $data['first_name'] : '';
        $last_name = isset($data['last_name']) ? $data['last_name'] : '';

        // Output buffering für Template
        ob_start();
        include $template_path;
        $content = ob_get_clean();

        if (empty($content)) {
            return new WP_Error(
                'template_empty',
                __('E-Mail-Template ist leer', 'newcustomer-discount')
            );
        }

        return $content;
    }

    /**
     * Generiert E-Mail-Header
     *
     * @param array $settings E-Mail-Einstellungen
     * @return array
     */
    private function generate_email_headers($settings) {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $settings['from_name'], $settings['from_email'])
        ];

        return apply_filters('ncd_email_headers', $headers, $settings);
    }

    /**
     * Setzt den Content-Type auf HTML
     *
     * @return string
     */
    public function set_html_content_type() {
        return 'text/html';
    }

    /**
     * Sendet eine Test-E-Mail
     *
     * @param string $email Test-Empfänger
     * @return bool|WP_Error
     */
    public function send_test_email($email) {
        $test_data = [
            'coupon_code' => 'TESTCODE123',
            'first_name' => 'Test',
            'last_name' => 'Kunde'
        ];

        return $this->send_discount_email($email, $test_data, [
            'subject' => '[TEST] ' . $this->default_settings['subject']
        ]);
    }

    /**
     * Validiert eine E-Mail-Vorlage
     *
     * @param string $template_content Template-Inhalt
     * @return bool|WP_Error
     */
    public function validate_template($template_content) {
        if (empty($template_content)) {
            return new WP_Error(
                'template_empty',
                __('Template-Inhalt ist leer', 'newcustomer-discount')
            );
        }

        // Prüfe auf erforderliche Platzhalter
        $required_placeholders = [
            '{coupon_code}',
            '{first_name}',
            '{last_name}'
        ];

        foreach ($required_placeholders as $placeholder) {
            if (strpos($template_content, $placeholder) === false) {
                return new WP_Error(
                    'missing_placeholder',
                    sprintf(__('Pflicht-Platzhalter %s fehlt', 'newcustomer-discount'), $placeholder)
                );
            }
        }

        return true;
    }

    /**
     * Speichert E-Mail-Versand in der Datenbank
     *
     * @param string $email Empfänger E-Mail
     * @param array $data E-Mail-Daten
     * @return void
     */
    private function log_email_sent($email, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'ncd_email_log';

        $wpdb->insert(
            $table,
            [
                'email' => $email,
                'coupon_code' => $data['coupon_code'],
                'sent_date' => current_time('mysql'),
                'status' => 'sent'
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Erstellt die E-Mail-Log Tabelle
     *
     * @return void
     */
    public static function create_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ncd_email_log';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            email varchar(255) NOT NULL,
            coupon_code varchar(50) NOT NULL,
            sent_date datetime DEFAULT NULL,
            status varchar(20) NOT NULL,
            PRIMARY KEY (id),
            KEY email (email),
            KEY coupon_code (coupon_code),
            KEY sent_date (sent_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Gibt die E-Mail-Logs zurück
     *
     * @param array $args Query-Argumente
     * @return array
     */
    public function get_email_logs($args = []) {
        global $wpdb;
        
        $defaults = [
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'sent_date',
            'order' => 'DESC'
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $table = $wpdb->prefix . 'ncd_email_log';
        
        return $wpdb->get_results($wpdb->prepare("
            SELECT *
            FROM $table
            ORDER BY {$args['orderby']} {$args['order']}
            LIMIT %d OFFSET %d
        ", $args['limit'], $args['offset']));
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
                '[NewCustomerDiscount] Email Sender Error: %s | Context: %s',
                $message,
                json_encode($context)
            ));
        }
    }
}